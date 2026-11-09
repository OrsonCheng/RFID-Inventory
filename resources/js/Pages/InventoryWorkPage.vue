<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" v-if="order">
    
    <!-- 1. 網路狀態橫幅 -->
    <NetworkBanner 
        :is-online="isOnline" 
        :is-syncing="isSyncing"
        :pending-count="pendingSyncQueue.length" 
        :is-simulated="isSimulatedOffline"
    />

    <!-- DEBUG 工具列 (僅供開發測試用) -->
    <div class="flex justify-end mb-2 mt-6">
		<!-- ★ 新增：Log 按鈕 -->
        <button 
            @click="showLogModal = true"
            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 hover:text-indigo-600 hover:border-indigo-200 shadow-sm transition-all"
        >
            <History class="w-3.5 h-3.5" />
            稽核日誌
        </button>
	
        <button 
            @click="toggleDebugOffline"
            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold border shadow-sm transition-all bg-white hover:shadow-md"
            :class="isSimulatedOffline ? 'text-rose-600 border-rose-200 bg-rose-50' : 'text-slate-500 border-slate-200 hover:text-slate-700'"
        >
            <Bug class="w-3.5 h-3.5" />
            {{ isSimulatedOffline ? 'DEBUG: 恢復連線' : 'DEBUG: 模擬斷網' }}
        </button>
    </div>

    <!-- 2. 頂部資訊卡片 -->
    <InventoryHeader 
        :order="order" 
        :progress="progressPercentage"
        :scanned-count="scannedCount"
        :total-count="order.items.length"
        :is-completed="isScanCompleted"
        :is-synced="isFullySynced"
    />

    <!-- 3. 內容切換區 -->
    <transition name="fade" mode="out-in">
        
        <!-- A. 完成結算畫面 (掃描完畢 + 資料同步完畢 + 非檢視模式) -->
        <InventorySummary 
            v-if="isScanCompleted && isFullySynced && !isReviewMode"
            :items="order.items"
            @review="isReviewMode = true"
        />

        <!-- B. 離線等待畫面 (掃描完畢 + 還有資料待上傳) -->
        <div v-else-if="isScanCompleted && !isFullySynced && !isReviewMode" class="bg-amber-50 rounded-2xl shadow-lg border-2 border-amber-200 p-12 text-center flex flex-col items-center">
            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mb-6 shadow-sm relative">
                <CloudOff class="w-10 h-10 text-amber-500" />
                <div class="absolute -top-1 -right-1 bg-rose-500 text-white text-xs font-bold w-8 h-8 flex items-center justify-center rounded-full border-2 border-white">
                    {{ pendingSyncQueue.length }}
                </div>
            </div>
            <h2 class="text-3xl font-black text-slate-800 mb-2">掃描完成，等待上傳</h2>
            <p class="text-slate-600 mb-2">您已完成所有資產的掃描，但尚有 <span class="font-bold text-rose-600">{{ pendingSyncQueue.length }} 筆資料</span> 暫存於本機。</p>
            <p class="text-sm text-slate-500 mb-8 bg-white px-4 py-2 rounded-lg border border-amber-100">
                <Wifi class="w-4 h-4 inline-block mr-1" />
                請移動至有網路的區域，系統將自動完成上傳。
            </p>
            <div class="flex gap-4">
                <button 
                    @click="syncOfflineData" 
                    :disabled="isSyncing"
                    class="px-8 py-3 bg-amber-500 text-white rounded-lg font-bold hover:bg-amber-600 shadow-lg shadow-amber-200 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <RefreshCw class="w-5 h-5" :class="{'animate-spin': isSyncing}" />
                    {{ isSyncing ? '正在嘗試連線...' : '手動重試上傳' }}
                </button>
                <button @click="isReviewMode = true" class="px-6 py-3 bg-white border border-amber-200 text-amber-700 rounded-lg font-bold hover:bg-amber-50 transition-colors">
                    檢視明細
                </button>
            </div>
        </div>

        <!-- C. 主要作業區 (掃描作業中) -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-280px)] min-h-[600px]">
            <!-- 左欄：掃描輸入 (檢視模式時隱藏輸入框，只顯示返回卡片) -->
            <div class="lg:col-span-5 flex flex-col gap-6">
                <InventoryScanner 
                    v-if="!isReviewMode"
                    ref="scannerRef"
                    :scan-state="scanState" 
                    :counts="{ scanned: scannedCount, unscanned: unscannedCount }"
                    :pending-count="pendingSyncQueue.length"
                    :queue-count="scanState.scanQueue.length"
                    :is-online="isOnline"
                    @keydown="handleKeydown"
                    @batch-import="enqueueBatchTags"
                />
                
                <!-- 檢視模式的返回卡片 -->
                <div v-else class="flex flex-col justify-center items-center bg-slate-50 rounded-2xl border border-slate-200 p-8 h-full">
                    <component :is="isFullySynced ? Trophy : CloudOff" class="w-16 h-16 mb-4" :class="isFullySynced ? 'text-emerald-500' : 'text-amber-500'" />
                    <h3 class="text-xl font-bold text-slate-800">盤點已完成</h3>
                    <p class="text-slate-500 mb-6" v-if="!isFullySynced">正在檢視明細 (尚有資料未同步)</p>
                    <p class="text-slate-500 mb-6" v-else>正在檢視明細</p>
                    <button @click="isReviewMode = false; nextTick(() => scannerRef?.focusInput())" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm hover:bg-slate-100 shadow-sm">
                        返回結算畫面
                    </button>
                </div>
            </div>

            <!-- 右欄：清單 -->
            <div class="lg:col-span-7">
                <InventoryList 
                    :items="order.items" 
                    @report-missing="openMissingModal"
                    @revert-missing="handleRevertMissing"
                />
            </div>
        </div>
    </transition>

    <!-- 盤虧申報 Modal -->
    <transition name="fade">
        <div v-if="showMissingModal" class="fixed inset-0 z-[100] flex items-center justify-center px-4">
            <!-- 遮罩 -->
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>
            
            <!-- 卡片 -->
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm relative z-10 overflow-hidden animate-bounce-small">
                <div class="bg-rose-50 p-6 border-b border-rose-100 text-center">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm text-rose-500">
                        <AlertTriangle class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-rose-900">回報資產盤虧</h3>
                    <p class="text-sm text-rose-700 mt-1 font-bold">{{ selectedItem?.fixed_asset?.name }}</p>
                    <p class="text-xs text-rose-400 mt-0.5">{{ selectedItem?.fixed_asset?.asset_tag }}</p>
                </div>
                
                <div class="p-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">遺失/未盤點原因</label>
                    <div class="space-y-2">
                        <!-- 快速選項 -->
                        <div class="flex flex-wrap gap-2 mb-3">
                            <button v-for="r in reasons" :key="r" @click="missingReason = r" 
                                class="px-3 py-1 text-xs rounded-full border transition-all"
                                :class="missingReason === r ? 'bg-rose-500 text-white border-rose-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-rose-300'"
                            >
                                {{ r }}
                            </button>
                        </div>
                        
                        <textarea 
                            v-model="missingReason" 
                            class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-rose-500 outline-none h-24 resize-none"
                            placeholder="請輸入詳細說明..."
                        ></textarea>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button @click="closeModal" class="flex-1 py-2.5 border border-slate-200 text-slate-600 rounded-lg font-bold text-sm hover:bg-slate-50">取消</button>
                        <button 
                            @click="confirmMissing" 
                            :disabled="!missingReason"
                            class="flex-1 py-2.5 bg-rose-600 text-white rounded-lg font-bold text-sm hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-rose-200"
                        >
                            確認盤虧
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
	
	<!-- ★ 新增：引入日誌 Modal -->
    <InventoryLogModal 
        :is-open="showLogModal" 
        :work-order-id="props.id"
        @close="showLogModal = false"
    />
	
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, reactive } from 'vue';
import axios from 'axios';
import { Bug, CloudOff, Wifi, RefreshCw, Trophy, AlertTriangle, History } from 'lucide-vue-next';

// 引入拆分出去的組件
import NetworkBanner from '../components/inventory/NetworkBanner.vue';
import InventoryHeader from '../components/inventory/InventoryHeader.vue';
import InventoryScanner from '../components/inventory/InventoryScanner.vue';
import InventoryList from '../components/inventory/InventoryList.vue';
import InventorySummary from '../components/inventory/InventorySummary.vue';
import InventoryLogModal from '../components/inventory/InventoryLogModal.vue'; // ★ 引入 Modal

// 引入邏輯 Hooks
import { useInventoryScan } from '../composables/useInventoryScan';
import { useOfflineSync } from '../composables/useOfflineSync';

const props = defineProps(['id']);
const order = ref(null);
const isReviewMode = ref(false);
const scannerRef = ref(null);
const showLogModal = ref(false); // ★ 控制 Modal 開關

// 1. 初始化離線同步邏輯
const { 
    isOnline, 
    isSimulatedOffline, 
    pendingSyncQueue, 
    isSyncing, 
    syncOfflineData, 
    toggleDebugOffline 
} = useOfflineSync(props.id, order);

// 2. 初始化掃描邏輯
// ★ 關鍵修正：使用 reactive 包裹回傳的 ref 物件，解決 [object Object] 顯示問題
const inventoryScanRaw = useInventoryScan(props.id, order, isOnline, pendingSyncQueue);
const scanState = reactive(inventoryScanRaw);

// 獨立解構出函數，因為函數不需要 reactive 包裹
const { handleKeydown, enqueueBatchTags } = inventoryScanRaw;

// 3. Computed Properties
const scannedCount = computed(() => order.value?.items.filter(i => i.status !== '未盤').length || 0);
// 盤虧也算在完成內 (只是狀態不同)
const unscannedCount = computed(() => order.value?.items.filter(i => i.status === '未盤').length || 0);

const progressPercentage = computed(() => {
    if (!order.value?.items?.length) return 0;
    // 進度 = (已盤 + 盤虧) / 總數
    const completedItems = order.value.items.filter(i => i.status === '已盤' || i.status === '盤虧').length;
    return Math.round((completedItems / order.value.items.length) * 100);
});

const isScanCompleted = computed(() => {
    // 只要沒有「未盤」的項目，就算完成 (包含盤虧)
    return order.value && unscannedCount.value === 0;
});

const isFullySynced = computed(() => pendingSyncQueue.value.length === 0);

// 4. 盤虧處理邏輯
const showMissingModal = ref(false);
const selectedItem = ref(null);
const missingReason = ref('');
const reasons = ['找不到資產', '資產維修中', '已報廢但未除帳', '借出未歸還'];

const openMissingModal = (item) => {
    selectedItem.value = item;
    missingReason.value = '';
    showMissingModal.value = true;
};

const closeModal = () => {
    showMissingModal.value = false;
    selectedItem.value = null;
};

const confirmMissing = async () => {
    if (!selectedItem.value || !missingReason.value) return;

    try {
        const res = await axios.post(`/api/inventory/work-orders/${props.id}/report-missing`, {
            item_id: selectedItem.value.id,
            reason: missingReason.value
        });
        
        // 更新本地資料
        const idx = order.value.items.findIndex(i => i.id === selectedItem.value.id);
        if (idx !== -1) {
            order.value.items[idx] = res.data;
        }
        
        closeModal();
    } catch (e) {
        alert('操作失敗: ' + (e.response?.data?.message || '未知錯誤'));
    }
};

const handleRevertMissing = async (item) => {
    if (!confirm(`確定要撤銷 "${item.fixed_asset.name}" 的盤虧狀態嗎？它將變回未盤點。`)) return;
    
    try {
        const res = await axios.post(`/api/inventory/work-orders/${props.id}/revert-missing`, {
            item_id: item.id
        });
        
        const idx = order.value.items.findIndex(i => i.id === item.id);
        if (idx !== -1) {
            order.value.items[idx] = res.data;
        }
		
		// ★★★ 關鍵修改：撤銷後，如果還有未盤點項目，自動退出檢視模式，讓左側變回掃描框 ★★★
        // 這裡不需要額外判斷，直接設為 false 即可。因為如果還剩其他已盤，v-if 會自動處理。
        isReviewMode.value = false;
		
		// 讓焦點回到掃描框
        nextTick(() => {
            scannerRef.value?.focusInput();
        });
		
    } catch (e) {
        alert('撤銷失敗: ' + (e.response?.data?.message || '未知錯誤'));
    }
};

// 5. 資料載入
const fetchOrder = async () => {
    try {
        const res = await axios.get(`/api/inventory/work-orders/${props.id}`);
        order.value = res.data;
    } catch (e) {
        alert('無法載入工單');
    }
};

onMounted(fetchOrder);
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>