<template>
    <transition name="slide-down">
        <!-- 離線警告 -->
        <div v-if="!isOnline" class="fixed top-0 left-0 w-full bg-rose-600 text-white z-50 py-2 px-4 text-center shadow-md flex items-center justify-center gap-2">
            <WifiOff class="w-5 h-5 animate-pulse" />
            <span class="font-bold tracking-wide">離線模式 {{ isSimulated ? '(DEBUG)' : '' }}</span>
            <span class="text-sm opacity-90">- 資料暫存中，請勿關閉頁面</span>
        </div>
        
        <!-- 同步中 -->
        <div v-else-if="isSyncing" class="fixed top-0 left-0 w-full bg-indigo-600 text-white z-50 py-2 px-4 text-center shadow-md flex items-center justify-center gap-2">
            <RefreshCw class="w-5 h-5 animate-spin" />
            <span class="font-bold tracking-wide">網路恢復，正在上傳 {{ pendingCount }} 筆資料...</span>
        </div>
    </transition>
</template>

<script setup>
import { WifiOff, RefreshCw } from 'lucide-vue-next';

defineProps({
    isOnline: Boolean,
    isSyncing: Boolean,
    pendingCount: Number,
    isSimulated: Boolean
});
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: transform 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); }
</style>