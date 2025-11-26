<template>
    <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden animate-bounce-small">
        <!-- Header: 依據是否有盤虧顯示不同顏色 -->
        <div class="p-8 text-white text-center relative overflow-hidden" 
             :class="hasMissingItems ? 'bg-amber-500' : 'bg-emerald-600'">
            
            <div class="absolute top-0 left-0 w-full h-full bg-white opacity-10" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
            
            <div class="relative z-10 flex flex-col items-center">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
                    <component :is="hasMissingItems ? AlertTriangle : Trophy" 
                               class="w-10 h-10" 
                               :class="hasMissingItems ? 'text-amber-500' : 'text-emerald-600'" />
                </div>
                
                <h2 class="text-3xl font-black mb-1">
                    {{ hasMissingItems ? '盤點完成 (含異常)' : '盤點任務完成！' }}
                </h2>
                <p class="opacity-90">
                    {{ hasMissingItems ? `尚有 ${missingCount} 筆資產標記為盤虧` : '所有資料已成功上傳至伺服器' }}
                </p>
            </div>
        </div>

        <div class="p-8">
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">本次作業效率分析</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <!-- 統計數據 -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                    <div class="text-xs text-slate-400 mb-1">實際耗時</div>
                    <div class="text-2xl font-black text-slate-700">{{ stats.durationStr }}</div>
                    <div class="text-[10px] text-slate-400">First - Last Scan</div>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                    <div class="text-xs text-slate-400 mb-1">平均速度</div>
                    <div class="text-2xl font-black text-indigo-600">{{ stats.itemsPerSec }}</div>
                    <div class="text-[10px] text-slate-400">件 / 秒</div>
                </div>
                <div class="p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl border border-indigo-100 text-center col-span-2 md:col-span-2 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-indigo-500 text-white text-[10px] px-2 py-0.5 rounded-bl-lg font-bold">效率提升</div>
                    <div class="text-xs text-indigo-400 mb-1 font-bold">預估節省工時</div>
                    <div class="text-3xl font-black text-indigo-600 flex items-center justify-center gap-2">
                        <Timer class="w-6 h-6" />
                        {{ stats.savedTimeStr }}
                    </div>
                    <div class="text-[10px] text-indigo-400 mt-1">相較傳統人工盤點</div>
                </div>
            </div>
            
            <div class="flex gap-4 justify-center">
                <router-link to="/inventory" class="px-8 py-3 bg-white border border-slate-300 text-slate-700 rounded-lg font-bold hover:bg-slate-50 transition-colors">
                    返回列表
                </router-link>
                
                <!-- ★ 關鍵按鈕：檢視明細 / 修正盤虧 -->
                <button 
                    @click="$emit('review')" 
                    class="px-8 py-3 rounded-lg font-bold shadow-lg transition-colors flex items-center gap-2"
                    :class="hasMissingItems ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-200' : 'bg-slate-800 hover:bg-slate-900 text-white'"
                >
                    <component :is="hasMissingItems ? Search : Eye" class="w-4 h-4" />
                    {{ hasMissingItems ? '檢視並修正盤虧' : '檢視明細' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Trophy, Timer, AlertTriangle, Eye, Search } from 'lucide-vue-next';

const props = defineProps(['items']);
defineEmits(['review']);

const MANUAL_TIME_PER_ITEM = 30;

const missingCount = computed(() => props.items.filter(i => i.status === '盤虧').length);
const hasMissingItems = computed(() => missingCount.value > 0);

const stats = computed(() => {
    // 統計時排除未盤點的 (理論上這時候應該是 0)
    const scanned = props.items.filter(i => i.status !== '未盤');
    const timestamps = scanned
        .map(i => i.scanned_at ? new Date(i.scanned_at).getTime() : 0)
        .filter(t => t > 0)
        .sort((a, b) => a - b);

    if (timestamps.length === 0) return { durationStr: '0秒', itemsPerSec: '∞', savedTimeStr: '0秒' };

    const startTime = timestamps[0];
    const endTime = timestamps[timestamps.length - 1];
    let durationSec = (endTime - startTime) / 1000;
    if (durationSec < 1) durationSec = 1;

    const totalItems = scanned.length;
    const itemsPerSec = (totalItems / durationSec).toFixed(1);
    const manualEstSec = totalItems * MANUAL_TIME_PER_ITEM;
    const savedSec = manualEstSec - durationSec;

    const formatTime = (sec) => {
        if (sec < 60) return `${Math.round(sec)}秒`;
        const m = Math.floor(sec / 60);
        const s = Math.round(sec % 60);
        return `${m}分${s}秒`;
    };

    return {
        durationStr: formatTime(durationSec),
        itemsPerSec: itemsPerSec,
        savedTimeStr: formatTime(savedSec > 0 ? savedSec : 0)
    };
});
</script>

<style scoped>
@keyframes bounce-small { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
.animate-bounce-small { animation: bounce-small 0.5s ease-in-out; }
</style>