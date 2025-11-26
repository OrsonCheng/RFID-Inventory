<template>
    <transition name="fade">
        <div class="fixed inset-0 z-[100] flex items-center justify-end" v-if="isOpen">
            <!-- 遮罩 (點擊關閉) -->
            <div class="absolute inset-0 bg-slate-900/30 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>
            
            <!-- 側邊滑出面板 -->
            <div class="relative z-10 w-full max-w-md h-full bg-white shadow-2xl flex flex-col transform transition-transform duration-300 slide-in">
                
                <!-- Header -->
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                            <ClipboardList class="w-5 h-5 text-indigo-600" />
                            操作稽核日誌
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">追蹤資產狀態變更與操作紀錄</p>
                    </div>
                    <button @click="$emit('close')" class="p-2 hover:bg-slate-200 rounded-full transition-colors text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Content (Timeline) -->
                <div class="flex-1 overflow-y-auto p-6 bg-white scrollbar-thin">
                    <div v-if="loading" class="flex justify-center py-10">
                        <Loader2 class="w-8 h-8 text-indigo-500 animate-spin" />
                    </div>

                    <div v-else-if="logs.length === 0" class="text-center py-10 text-slate-400">
                        <Inbox class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="text-sm">暫無操作紀錄</p>
                    </div>

                    <div v-else class="space-y-8 relative pl-2">
                        <!-- 時間軸線 -->
                        <div class="absolute top-2 bottom-2 left-[19px] w-0.5 bg-slate-100"></div>

                        <div v-for="log in logs" :key="log.id" class="relative flex gap-4 group">
                            <!-- 時間點 Icon -->
                            <div class="relative z-10 w-10 h-10 rounded-full flex items-center justify-center border-4 border-white shadow-sm shrink-0" :class="getIconBg(log.action)">
                                <component :is="getIcon(log.action)" class="w-5 h-5" :class="getIconColor(log.action)" />
                            </div>

                            <!-- 內容卡片 -->
                            <div class="flex-1 min-w-0 pt-1">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full" :class="getBadgeClass(log.action)">
                                        {{ getActionText(log.action) }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </span>
                                </div>
                                
                                <p class="text-sm font-bold text-slate-700 truncate">
                                    {{ log.fixed_asset?.name || '未知資產' }}
                                </p>
                                <p class="text-xs text-slate-400 font-mono mb-2">
                                    {{ log.fixed_asset?.asset_tag }}
                                </p>

                                <!-- 備註區塊 (如果有) -->
                                <div v-if="log.note" class="bg-slate-50 p-2 rounded-lg border border-slate-100 text-xs text-slate-600 italic">
                                    "{{ log.note }}"
                                </div>

                                <div class="mt-2 flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600">
                                        {{ log.user?.name?.substring(0,1) }}
                                    </div>
                                    <span class="text-xs text-slate-500">{{ log.user?.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-slate-100 bg-slate-50 text-center">
                    <p class="text-[10px] text-slate-400">僅顯示最近 100 筆紀錄</p>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import { 
    X, ClipboardList, Loader2, Inbox, 
    ScanLine, AlertTriangle, RotateCcw, CheckCircle2 
} from 'lucide-vue-next';

const props = defineProps({
    isOpen: Boolean,
    workOrderId: [Number, String]
});

defineEmits(['close']);

const logs = ref([]);
const loading = ref(false);

const fetchLogs = async () => {
    if (!props.workOrderId) return;
    loading.value = true;
    try {
        const res = await axios.get(`/api/inventory/work-orders/${props.workOrderId}/logs`);
        logs.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

// 當 Modal 打開時，重新抓取資料
watch(() => props.isOpen, (newVal) => {
    if (newVal) fetchLogs();
});

// --- Helper Functions ---

const getActionText = (action) => {
    const map = {
        'scan': '掃描盤點',
        'batch_scan': '批次盤點',
        'report_missing': '申報盤虧',
        'revert_manual': '手動撤銷',
        'recover_found': '掃描找回'
    };
    return map[action] || action;
};

const getIcon = (action) => {
    if (action.includes('missing')) return AlertTriangle;
    if (action.includes('revert')) return RotateCcw;
    if (action.includes('recover')) return CheckCircle2;
    return ScanLine;
};

const getIconBg = (action) => {
    if (action === 'report_missing') return 'bg-rose-100';
    if (action === 'recover_found') return 'bg-emerald-100';
    if (action === 'revert_manual') return 'bg-amber-100';
    return 'bg-indigo-50'; // scan
};

const getIconColor = (action) => {
    if (action === 'report_missing') return 'text-rose-600';
    if (action === 'recover_found') return 'text-emerald-600';
    if (action === 'revert_manual') return 'text-amber-600';
    return 'text-indigo-600';
};

const getBadgeClass = (action) => {
    if (action === 'report_missing') return 'bg-rose-100 text-rose-700';
    if (action === 'recover_found') return 'bg-emerald-100 text-emerald-700';
    if (action === 'revert_manual') return 'bg-amber-100 text-amber-700';
    return 'bg-indigo-50 text-indigo-700';
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-in { animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>