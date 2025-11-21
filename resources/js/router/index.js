import { createRouter, createWebHistory } from 'vue-router';
import AssetManagement from '../Pages/AssetManagement.vue';
import InventoryDashboard from '../Pages/InventoryDashboard.vue';
import InventoryWorkPage from '../Pages/InventoryWorkPage.vue';

const routes = [
    {
        path: '/',
        redirect: '/assets',
    },
    {
        path: '/assets',
        name: 'AssetManagement',
        component: AssetManagement,
    },
    {
        path: '/inventory',
        name: 'InventoryDashboard',
        component: InventoryDashboard,
    },
    {
        path: '/inventory/:id',
        name: 'InventoryWorkPage',
        component: InventoryWorkPage,
        props: true, // 將路由參數 :id 作為 props 傳入元件
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;

// 備註：您需要一個主 App.vue 來包含 <router-view> 和導航欄