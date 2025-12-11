import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true; // 確保攜帶 Cookie (Session)

// ★★★ 關鍵修正：設定 API 前綴 ★★★
// 根據編譯模式判斷：
// - 正式環境 (Production): 使用 '/rfid-inventory'
// - 開發環境 (Development): 使用空字串 (根目錄)
const appPrefix = import.meta.env.MODE === 'production' ? '/rfid-inventory' : '';

// 移除 baseURL 設定，避免與攔截器重複前綴
window.axios.defaults.baseURL = ''; 

// ★★★ 強力修正：Request 攔截器 ★★★
// 負責修正所有內部請求的路徑 (無論相對或絕對)，確保只有一個前綴。
window.axios.interceptors.request.use(config => {
    const url = config.url;

    if (appPrefix && url) {
        // 確保請求不是外部 URL (不以 http/https 開頭)
        if (!url.startsWith('http') && !url.startsWith('https')) {
            // 拼接路徑：確保前綴沒有斜線，後面的 URL 也沒有斜線，然後用單一斜線連接
            // 例如: /rfid-inventory + /api/user => /rfid-inventory/api/user
            // 例如: api/user => /rfid-inventory/api/user
            config.url = appPrefix.replace(/\/$/, '') + '/' + url.replace(/^\//, '');
        }
    }
    return config;
}, error => {
    return Promise.reject(error);
});