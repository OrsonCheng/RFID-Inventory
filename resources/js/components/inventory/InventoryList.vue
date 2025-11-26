<template>
    <div class="flex flex-col bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-full">
        <!-- Tabs -->
        <div class="flex border-b border-slate-100 bg-slate-50/50">
            <!-- 待盤點 Tab -->
            <button 
                @click="currentTab = 'unscanned'" 
                class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all" 
                :class="currentTab === 'unscanned' ? 'border-amber-500 text-amber-700 bg-amber-50/30' : 'border-transparent text-slate-400'"
            >
                <ListFilter class="w-4 h-4" /> 待盤點 
                <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1">{{ unscanned.length }}</span>
            </button>
            
            <!-- 已盤紀錄 Tab -->
            <button 
                @click="currentTab = 'scanned'" 
                class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all" 
                :class="currentTab === 'scanned' ? 'border-emerald-500 text-emerald-700 bg-emerald-50/30' : 'border-transparent text-slate-400'"
            >
                <History class="w-4 h-4" /> 已盤紀錄 
                <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1">{{ scanned.length }}</span>
            </button>
            
            <!-- 盤虧異常 Tab -->
            <button 
                @click="currentTab = 'missing'" 
                class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all" 
                :class="currentTab === 'missing' ? 'border-rose-500 text-rose-700 bg-rose-50/30' : 'border-transparent text-slate-400'"
            >
                <AlertCircle class="w-4 h-4" /> 盤虧異常 
                <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1">{{ missing.length }}</span>
            </button>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/30 scrollbar-thin">
            <transition-group name="list">
                <div v-for="item in currentList" :key="item.id" class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center group relative overflow-hidden">
                    
                    <!-- 離線標記背景 (僅在離線待同步時顯示) -->
                    <div v-if="item.status === 'offline_pending'" class="absolute inset-0 bg-amber-50/50 pointer-events-none z-0"></div>

                    <!-- 左側資訊 -->
                    <div class="flex items-start gap-3 z-10">
                        <div class="mt-1">
                            <!-- 根據狀態顯示不同圖標 -->
                            <div v-if="item.status === '已盤'" class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                <Check class="w-5 h-5 text-emerald-600" />
                            </div>
                            <div v-else-if="item.status === 'offline_pending'" class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center animate-pulse">
                                <CloudOff class="w-5 h-5 text-amber-600" />
                            </div>
                            <div v-else-if="item.status === '盤虧'" class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center">
                                <HelpCircle class="w-5 h-5 text-rose-600" />
                            </div>
                            <div v-else class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center">
                                <Package class="w-5 h-5 text-amber-400" />
                            </div>
                        </div>
                        
                        <div>
                            <div class="font-bold text-slate-800">{{ item.fixed_asset.name || '資料同步中...' }}</div>
                            <div class="text-xs font-mono text-slate-400 flex items-center gap-2 mt-0.5">
                                <span class="bg-slate-100 px-1.5 rounded">{{ item.fixed_asset.asset_tag }}</span>
                                <span v-if="item.fixed_asset.rfid_tag" class="text-indigo-400 flex items-center gap-0.5">
                                    <Wifi class="w-3 h-3" /> {{ item.fixed_asset.rfid_tag }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                <MapPin class="w-3 h-3" /> {{ item.fixed_asset.location || '位置未詳' }}
                            </div>
                            
                            <!-- 顯示盤虧原因 -->
                            <div v-if="item.status === '盤虧'" class="text-xs text-rose-500 font-bold mt-1 flex items-center gap-1">
                                原因: {{ item.note }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- 右側操作按鈕 -->
                    <div class="z-10 text-right">
                        <!-- 狀態 A: 未盤點 -> 顯示「報失」按鈕 (hover 時才顯示，保持畫面乾淨) -->
                        <button 
                            v-if="item.status === '未盤'" 
                            @click="$emit('report-missing', item)"
                            class="opacity-0 group-hover:opacity-100 transition-opacity px-3 py-1.5 bg-white border border-rose-200 text-rose-600 text-xs font-bold rounded-lg hover:bg-rose-50 shadow-sm"
                        >
                            回報盤虧
                        </button>

                        <!-- 狀態 B: 盤虧 -> 顯示「撤銷」按鈕 -->
                        <button 
                            v-else-if="item.status === '盤虧'" 
                            @click="$emit('revert-missing', item)"
                            class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 text-xs font-bold rounded-lg hover:bg-slate-50 shadow-sm"
                        >
                            撤銷
                        </button>

                        <!-- 狀態 C: 已盤點/同步中 (顯示狀態標籤) -->
                        <div v-else-if="item.status === '已盤'">
                            <span class="text-emerald-600 text-xs font-bold bg-emerald-50 px-2 py-1 rounded-full border border-emerald-100">已完成</span>
                        </div>
                        <div v-else-if="item.status === 'offline_pending'">
                            <span class="text-amber-600 text-xs font-bold bg-amber-100 px-2 py-1 rounded-full border border-amber-200 flex items-center gap-1">
                                <RefreshCw class="w-3 h-3 animate-spin" /> 待同步
                            </span>
                        </div>
                    </div>
                </div>
            </transition-group>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ListFilter, History, Check, CloudOff, Package, Wifi, MapPin, RefreshCw, AlertCircle, HelpCircle } from 'lucide-vue-next';

const props = defineProps(['items']);
defineEmits(['report-missing', 'revert-missing']);

const currentTab = ref('unscanned');

// 分類邏輯
const unscanned = computed(() => props.items?.filter(i => i.status === '未盤') || []);

// 已盤 + 離線待同步 都算在「已盤紀錄」
const scanned = computed(() => props.items?.filter(i => i.status === '已盤' || i.status === 'offline_pending').sort((a,b) => {
    const dateA = a.scanned_at ? new Date(a.scanned_at) : new Date();
    const dateB = b.scanned_at ? new Date(b.scanned_at) : new Date();
    return dateB - dateA;
}) || []);

// 盤虧列表
const missing = computed(() => props.items?.filter(i => i.status === '盤虧') || []);

const currentList = computed(() => {
    if (currentTab.value === 'missing') return missing.value;
    if (currentTab.value === 'scanned') return scanned.value;
    return unscanned.value;
});
</script>

<style scoped>
.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateX(20px); }
.scrollbar-thin::-webkit-scrollbar { width: 6px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>