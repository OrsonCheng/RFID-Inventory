<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-900">
    
    <!-- ★★★ 導航欄 (只有登入後才顯示) ★★★ -->
    <nav v-if="authStore.isAuthenticated" class="bg-slate-900 shadow-lg sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          
          <!-- Logo -->
          <div class="flex items-center gap-3">
            <div class="bg-indigo-500 p-2 rounded-lg">
              <ScanBarcode class="w-6 h-6 text-white" />
            </div>
            <span class="font-bold text-xl text-white tracking-wide hidden md:block">固定資產 RFID 系統</span>
          </div>

          <!-- 選單連結 -->
          <div class="hidden md:block">
            <div class="ml-10 flex items-baseline space-x-4">
              <router-link to="/assets" active-class="bg-slate-800 text-white" class="nav-link">
                <Package class="w-4 h-4" /> 資產管理
              </router-link>
              <router-link to="/inventory" active-class="bg-slate-800 text-white" class="nav-link">
                <ClipboardList class="w-4 h-4" /> 盤點工單
              </router-link>
              <router-link to="/calibrations" active-class="bg-slate-800 text-white" class="nav-link">
                <Activity class="w-4 h-4" /> 校驗紀錄
              </router-link>
            </div>
          </div>

          <!-- ★★★ 右側：使用者資訊 ★★★ -->
          <div class="flex items-center gap-4">
             <div class="text-right hidden sm:block" v-if="authStore.user">
                <div class="text-sm font-bold text-white flex items-center justify-end gap-2">
                    {{ authStore.user.name }}
                    <!-- 顯示角色標籤 (例如: Admin, Senior) -->
                    <span v-for="role in authStore.user.roles" :key="role" 
                          class="bg-emerald-500 text-white text-[10px] px-1.5 py-0.5 rounded uppercase tracking-wider shadow-sm">
                        {{ role }}
                    </span>
                </div>
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
    <main :class="{'max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 fade-in': authStore.isAuthenticated}">
      <router-view></router-view>
    </main>
  </div>
</template>

<script setup>
import { ScanBarcode, Package, ClipboardList, LogOut, Shield, Activity } from 'lucide-vue-next';
import { useAuthStore } from './stores/auth';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

const handleLogout = async () => {
    if(confirm('確定要登出系統嗎？')) {
        await authStore.logout();
    }
};
</script>

<style>
.nav-link {
    @apply text-slate-300 hover:bg-slate-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2;
}
.fade-in { animation: fadeIn 0.5s ease-in-out; }
@keyframes fadeIn { 
    from { opacity: 0; transform: translateY(10px); } 
    to { opacity: 1; transform: translateY(0); } 
}
</style>