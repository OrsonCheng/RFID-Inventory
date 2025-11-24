<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-900">
    
    <!-- ★★★ 導航欄 (只有登入後才顯示) ★★★ -->
    <nav v-if="authStore.isAuthenticated" class="bg-slate-900 shadow-lg sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          
          <!-- 左側：Logo 與標題 -->
          <div class="flex items-center gap-3">
            <div class="bg-indigo-500 p-2 rounded-lg">
              <ScanBarcode class="w-6 h-6 text-white" />
            </div>
            <span class="font-bold text-xl text-white tracking-wide hidden md:block">固定資產 RFID 系統</span>
          </div>

          <!-- 中間：選單連結 -->
          <div class="hidden md:block">
            <div class="ml-10 flex items-baseline space-x-4">
              <router-link to="/assets" active-class="bg-slate-800 text-white" class="nav-link">
                <Package class="w-4 h-4" /> 資產管理
              </router-link>
              <router-link to="/inventory" active-class="bg-slate-800 text-white" class="nav-link">
                <ClipboardList class="w-4 h-4" /> 盤點工單
              </router-link>
            </div>
          </div>

          <!-- ★★★ 右側：使用者資訊與登出按鈕 ★★★ -->
          <div class="flex items-center gap-4">
             <!-- 使用者資訊 (確認 user 資料存在才顯示) -->
             <div class="text-right hidden sm:block" v-if="authStore.user">
                <div class="text-sm font-bold text-white">{{ authStore.user.name }}</div>
                <div class="text-xs text-slate-400">{{ authStore.user.email }}</div>
             </div>
             
             <!-- 登出按鈕 -->
             <button 
                @click="handleLogout" 
                class="bg-slate-800 p-2 rounded-full text-slate-400 hover:text-white hover:bg-rose-600 transition-all border border-slate-700" 
                title="登出系統"
             >
                <LogOut class="w-5 h-5" />
             </button>
          </div>
        </div>
      </div>
    </nav>

    <!-- 主內容區 -->
    <!-- 動態 Class：如果有登入，給予標準的寬度限制與 Padding；沒登入(Login頁)則全寬 -->
    <main :class="{'max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 fade-in': authStore.isAuthenticated}">
      <router-view></router-view>
    </main>
  </div>
</template>

<script setup>
import { ScanBarcode, Package, ClipboardList, LogOut } from 'lucide-vue-next';
import { useAuthStore } from './stores/auth';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

// 處理登出邏輯
const handleLogout = async () => {
    if(confirm('確定要登出系統嗎？')) {
        await authStore.logout();
        // logout action 執行完後，會自動跳轉回 /login
    }
};
</script>

<style>
/* 定義導航連結的樣式 */
.nav-link {
    @apply text-slate-300 hover:bg-slate-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2;
}

/* 簡單的淡入動畫 */
.fade-in { animation: fadeIn 0.5s ease-in-out; }
@keyframes fadeIn { 
    from { opacity: 0; transform: translateY(10px); } 
    to { opacity: 1; transform: translateY(0); } 
}
</style>