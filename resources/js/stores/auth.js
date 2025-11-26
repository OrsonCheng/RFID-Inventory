import { defineStore } from 'pinia';
import axios from 'axios';
import router from '../router';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('auth_token') || null,
        loading: false,
        error: null
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        
        // 判斷是否為 Admin
        isAdmin: (state) => {
            return state.user?.roles?.includes('admin') || false;
        },

        // ★★★ UI 判斷邏輯 ★★★
        
        // 1. 是否能管理資產 (顯示新增、編輯、導入按鈕)
        canManageAssets: (state) => {
            if (!state.user || !state.user.roles) return false;
            return state.user.roles.includes('admin') || state.user.roles.includes('senior_staff');
        },

        // 2. 是否能生成工單 (顯示生成按鈕)
        canCreateOrder: (state) => {
            if (!state.user || !state.user.roles) return false;
            return state.user.roles.includes('admin') || state.user.roles.includes('senior_staff');
        }
    },

    actions: {
        // 本地登入 (Legacy / 傳統帳密登入)
        async login(credentials) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.post('/api/login', credentials);
                const { access_token, user } = response.data;
                this.setUserData(access_token, user);
                router.push('/assets');
            } catch (err) {
                this.error = err.response?.data?.message || '登入失敗';
            } finally {
                this.loading = false;
            }
        },

        // 設定使用者資料 (共用)
        setUserData(token, user) {
            this.token = token;
            this.user = user;
            localStorage.setItem('auth_token', token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },

        // 清除本地資料 (內部用)
        clearUserData() {
            this.user = null;
            this.token = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];
        },

        // SSO 登出邏輯
        async logout() {
            try {
                if (this.token) {
                    // 1. 呼叫後端登出 API
                    // 對應後端的 routes/api.php: Route::post('/logout', ...)
                    const response = await axios.post('/api/logout');

                    // 2. 清除本地狀態 (無論後端回應如何，先清乾淨)
                    this.clearUserData();

                    // 3. 檢查後端是否回傳 SSO 登出網址
                    // 對應 SSOController 回傳的 { "logout_url": "..." }
                    if (response.data && response.data.logout_url) {
                        // 如果有網址，讓瀏覽器執行「整頁跳轉」去 SSO Server 進行登出
                        window.location.href = response.data.logout_url;
                    } else {
                        // 如果後端沒給網址 (例如純本地登出)，則回到登入頁
                        router.push('/login');
                    }
                } else {
                    // 如果原本就沒有 token，直接回登入頁
                    router.push('/login');
                }
            } catch (e) {
                console.error("Logout API failed, forcing local logout...", e);
                // API 呼叫失敗 (例如網路問題或 Token 過期)，強制本地登出
                this.clearUserData();
                router.push('/login');
            }
        },

        // 檢查登入狀態
        async checkAuth() {
            if (!this.token) return;
            try {
                // 確保 axios 有 token
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
                
                // 修改：對應 routes/api.php 裡的 Route::get('/user')
                // 除非你有特別定義 /api/me，否則 Laravel 預設通常是 /api/user
                const response = await axios.get('/api/user');
                
                this.user = response.data;
            } catch (err) {
                // 如果 Token 失效 (401)，觸發登出
                this.clearUserData();
                router.push('/login');
                // throw err; // 選擇性：視前端是否需要捕捉錯誤顯示 UI
            }
        }
    }
});