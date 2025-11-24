<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-100">
    <div class="text-center p-8 bg-white rounded-2xl shadow-lg border border-slate-200">
      <!-- 轉圈圈動畫 -->
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
      
      <h2 class="text-xl font-bold text-slate-800 mb-2">正在驗證身分...</h2>
      <p class="text-slate-500 text-sm">TE Auth Center 授權成功，正在進入系統</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

onMounted(async () => {
    // 1. 從網址參數取得 Token (由後端 SSOController 傳過來的)
    const token = route.query.token;

    if (token) {
        // 2. 存入 Pinia 狀態
        authStore.token = token;
        
        // 3. 持久化存入 LocalStorage (確保重整後還在)
        localStorage.setItem('auth_token', token);
        
        // 4. 設定 Axios 預設標頭 (之後的 API 請求都會帶上這個 Token)
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // 5. 呼叫 /api/me 確認 Token 有效並取得使用者資料 (包含名字、權限)
        try {
            await authStore.checkAuth();
            
            // 6. 一切順利，跳轉到資產管理首頁
            router.push('/assets');
        } catch (e) {
            console.error('Token 驗證失敗', e);
            alert('登入驗證失敗，請重新嘗試');
            router.push('/login');
        }
    } else {
        // 如果網址沒有 Token，代表是不正常進入，踢回登入頁
        console.warn('No token found in URL');
        router.push('/login');
    }
});
</script>