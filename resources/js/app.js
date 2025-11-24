import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia'; // 1. 引入 createPinia
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth'; // 引入 Auth Store

const app = createApp(App);
const pinia = createPinia(); // 2. 建立 Pinia 實例

// ★★★ 3. 關鍵順序：必須先安裝 Pinia ★★★
app.use(pinia);

// ★★★ 4. 然後安裝 Router (Router 守衛可能會用到 Store) ★★★
app.use(router);

// ★★★ 5. 現在 Pinia 已經啟動，可以安全呼叫 Store 了 ★★★
const authStore = useAuthStore();

// 6. 在掛載 App 之前，先檢查是否有登入 Token (避免畫面閃爍)
// checkAuth 會看 LocalStorage 有沒有 Token，有的話去後端驗證
authStore.checkAuth().finally(() => {
    // 7. 無論驗證成功或失敗，最後才掛載 Vue 實例
    app.mount('#app');
});