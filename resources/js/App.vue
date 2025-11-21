<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-900">
    <!-- 頂部導航欄 -->
    <nav class="bg-slate-900 shadow-lg sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo 區域 -->
          <div class="flex items-center gap-3">
            <div class="bg-indigo-500 p-2 rounded-lg">
              <ScanBarcode class="w-6 h-6 text-white" />
            </div>
            <span class="font-bold text-xl text-white tracking-wide">固定資產 RFID 系統</span>
          </div>

          <!-- 導航連結 -->
          <div class="hidden md:block">
            <div class="ml-10 flex items-baseline space-x-4">
              <router-link 
                to="/assets" 
                active-class="bg-slate-800 text-white"
                class="text-slate-300 hover:bg-slate-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2"
              >
                <Package class="w-4 h-4" /> 資產管理
              </router-link>

              <router-link 
                to="/inventory" 
                active-class="bg-slate-800 text-white"
                class="text-slate-300 hover:bg-slate-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2"
              >
                <ClipboardList class="w-4 h-4" /> 盤點工單
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- 主內容區 -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 fade-in">
      <router-view></router-view>
    </main>
  </div>
</template>

<script setup>
import { ScanBarcode, Package, ClipboardList } from 'lucide-vue-next';
</script>

<style>
/* 簡單的轉場動畫 */
.fade-in {
  animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>