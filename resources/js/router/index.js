import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth'; // 確保這路徑對應您的檔案結構

// 引入頁面元件
import Login from '../Pages/Login.vue';
import AuthCallback from '../Pages/AuthCallback.vue';
import AssetManagement from '../Pages/AssetManagement.vue';
import InventoryDashboard from '../Pages/InventoryDashboard.vue';
import InventoryWorkPage from '../Pages/InventoryWorkPage.vue';

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { guest: true }
    },
    {
        path: '/auth/callback',
        name: 'AuthCallback',
        component: AuthCallback,
        // 登入回調頁面不需要權限檢查
    },
    {
        path: '/',
        redirect: '/assets',
    },
    {
        path: '/assets',
        name: 'AssetManagement',
        component: AssetManagement,
        meta: { requiresAuth: true }
    },
    {
        path: '/inventory',
        name: 'InventoryDashboard',
        component: InventoryDashboard,
        meta: { requiresAuth: true }
    },
    {
        path: '/inventory/:id',
        name: 'InventoryWorkPage',
        component: InventoryWorkPage,
        props: true,
        meta: { requiresAuth: true }
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// ★★★ 全局路由守衛 (增強版) ★★★
router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    
    // 1. 雙重保險：如果 Pinia 裡沒有 Token，嘗試從 LocalStorage 恢復
    // 這能避免頁面刷新時，因 Pinia 被重置而導致誤判為未登入，或反之
    if (!authStore.token) {
        const storedToken = localStorage.getItem('auth_token');
        if (storedToken) {
            authStore.token = storedToken;
        }
    }

    // 2. 判斷最終認證狀態
    const isAuthenticated = !!authStore.token;

    // Debug Log (您可以在瀏覽器 Console 看到這些訊息)
    console.log(`路由跳轉: ${from.path} -> ${to.path}`);
    console.log(`登入狀態: ${isAuthenticated ? '已登入' : '未登入'}`);
    console.log(`頁面需求: ${to.meta.requiresAuth ? '需權限' : '公開'}`);

    // 3. 守衛邏輯
    if (to.meta.requiresAuth && !isAuthenticated) {
        // 情況 A: 去受保護頁面 + 未登入 -> 踢去 Login
        console.warn('攔截：未登入，強制跳轉至登入頁');
        next({ name: 'Login' });
    } 
    else if (to.meta.guest && isAuthenticated) {
        // 情況 B: 去登入頁 + 已登入 -> 踢回首頁
        console.warn('攔截：已登入，重導向至首頁');
        next({ name: 'AssetManagement' });
    } 
    else {
        // 情況 C: 通行
        next();
    }
});

export default router;