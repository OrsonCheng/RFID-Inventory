<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 space-y-8 bg-slate-50 min-h-screen">
    
    <!-- 1. 頂部導航與操作區 -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
          <div class="p-2 bg-indigo-600 rounded-lg shadow-lg shadow-indigo-200">
            <ClipboardCheck class="w-6 h-6 text-white" />
          </div>
          盤點工單中心
        </h1>
        <p class="text-slate-500 text-sm mt-2 ml-1">監控盤點進度、審核差異與管理盤點排程</p>
      </div>
      
      <!-- 生成工單按鈕 (權限控制) -->
      <div v-if="authStore && authStore.canCreateOrder">
        <button 
            @click="showCreateModal = true"
            class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 hover:shadow-indigo-200 hover:shadow-lg transition-all shadow-md active:scale-95"
        >
          <PlusCircle class="w-5 h-5" />
          <span>發起新盤點</span>
        </button>
      </div>
    </div>

    <!-- 2. 關鍵指標儀表板 (Metrics) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <!-- 總工單數 -->
      <div class="stat-card border-l-4 border-slate-500">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">本月工單總數</p>
        <div class="flex items-baseline gap-2">
          <h3 class="text-3xl font-black text-slate-800">{{ workOrders.length }}</h3>
          <span class="text-xs text-slate-500">單</span>
        </div>
      </div>

      <!-- 進行中 (In Progress) -->
      <div class="stat-card border-l-4 border-blue-500">
        <p class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1">進行中任務</p>
        <div class="flex items-baseline gap-2">
          <h3 class="text-3xl font-black text-blue-600">{{ stats.inProgress }}</h3>
          <span class="text-xs text-slate-500">單</span>
        </div>
        <div class="text-[10px] text-slate-400 mt-1">需持續追蹤</div>
      </div>

      <!-- 待盤資產 (Pending Items) -->
      <div class="stat-card border-l-4 border-amber-500">
        <p class="text-xs font-bold text-amber-500 uppercase tracking-wider mb-1">待盤點資產數</p>
        <div class="flex items-baseline gap-2">
          <h3 class="text-3xl font-black text-amber-500">{{ stats.totalUnscanned }}</h3>
          <span class="text-xs text-slate-500">件</span>
        </div>
        <div class="text-[10px] text-slate-400 mt-1">來自所有進行中工單</div>
      </div>

      <!-- 完成率 (Completion Rate) -->
      <div class="stat-card border-l-4 border-emerald-500">
        <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">總體完成率</p>
        <div class="flex items-baseline gap-2">
          <h3 class="text-3xl font-black text-emerald-600">{{ stats.completionRate }}%</h3>
        </div>
        <!-- 迷你進度條 -->
        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2">
            <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-1000" :style="{ width: stats.completionRate + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- 3. 過濾器 (Filter Bar) -->
    <div class="bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row gap-2 items-center">
      <div class="relative flex-1 w-full group">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
          <Search class="w-5 h-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
        </div>
        <input 
          v-model="searchQuery" 
          type="text" 
          class="block w-full pl-11 pr-4 py-3 bg-transparent border-none rounded-xl text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:text-sm transition-all focus:bg-slate-50" 
          placeholder="搜尋工單號、保管人或標題..."
        >
      </div>
      
      <div class="flex bg-slate-100 p-1 rounded-xl">
        <button 
            v-for="tab in ['全部', '進行中', '已完成']" 
            :key="tab"
            @click="filterStatus = tab"
            class="px-4 py-2 text-xs font-bold rounded-lg transition-all"
            :class="filterStatus === tab ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        >
            {{ tab }}
        </button>
      </div>
    </div>

    <!-- 4. 工單列表 (Rich Table) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">工單資訊 / 類型</th>
                        <th class="px-6 py-4">保管人 / 負責單位</th>
                        <th class="px-6 py-4 w-1/4">盤點進度</th>
                        <th class="px-6 py-4">狀態</th>
                        <th class="px-6 py-4">建立日期</th>
                        <th class="px-6 py-4 text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading">
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <Loader2 class="w-8 h-8 animate-spin mx-auto mb-2" />
                            資料讀取中...
                        </td>
                    </tr>
                    <tr v-else-if="filteredOrders.length === 0">
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <Inbox class="w-12 h-12 mx-auto mb-2 opacity-30" />
                            尚無相關工單
                        </td>
                    </tr>
                    <tr v-for="order in filteredOrders" :key="order.id" class="group hover:bg-indigo-50/30 transition-colors">
                        <!-- 工單資訊 -->
                        <td class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-1">
                                    <!-- 根據標題判斷類型圖示 -->
                                    <div v-if="isAnnualAudit(order.title)" class="p-2 bg-purple-100 text-purple-600 rounded-lg" title="年度盤點 (高重要性)">
                                        <Flag class="w-5 h-5" />
                                    </div>
                                    <div v-else class="p-2 bg-blue-100 text-blue-600 rounded-lg" title="例行盤點">
                                        <CalendarClock class="w-5 h-5" />
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">
                                        {{ order.title }}
                                    </div>
                                    <div class="text-xs text-slate-500 font-mono mt-0.5 flex items-center gap-2">
                                        <span>#{{ order.id.toString().padStart(6, '0') }}</span>
                                        <!-- 模擬類型標籤 -->
                                        <span v-if="isAnnualAudit(order.title)" class="px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded border border-purple-100 text-[10px] font-bold">年度大盤</span>
                                        <span v-else class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded border border-blue-100 text-[10px] font-bold">月度自盤</span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- 保管人 -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600 border-2 border-white shadow-sm">
                                    {{ order.responsible_user?.name?.charAt(0) }}
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-700">{{ order.responsible_user?.name }}</div>
                                    <div class="text-xs text-slate-400">TE Dept.</div>
                                </div>
                            </div>
                        </td>

                        <!-- 進度條 (使用後端回傳的 scanned_count) -->
                        <td class="px-6 py-4">
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-xs font-bold text-slate-600">{{ calculateProgress(order) }}%</span>
                                <span class="text-xs text-slate-400">{{ order.scanned_count || 0 }} / {{ order.items_count }}</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div 
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="getProgressColor(calculateProgress(order))"
                                    :style="{ width: calculateProgress(order) + '%' }"
                                ></div>
                            </div>
                        </td>

                        <!-- 狀態 -->
                        <td class="px-6 py-4">
                            <span :class="statusBadgeClass(order.status)">
                                {{ statusLabel(order.status) }}
                            </span>
                        </td>

                        <!-- 日期 -->
                        <td class="px-6 py-4">
                            <div class="text-sm text-slate-600">{{ new Date(order.created_at).toLocaleDateString() }}</div>
                            <div class="text-xs text-slate-400">{{ getTimeAgo(order.created_at) }}</div>
                        </td>

                        <!-- 操作 -->
                        <td class="px-6 py-4 text-right">
                            <router-link 
                                :to="{ name: 'InventoryWorkPage', params: { id: order.id } }"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-bold rounded-lg hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm"
                            >
                                <ScanLine class="w-3.5 h-3.5" />
                                進入盤點
                            </router-link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. 生成工單 Modal (樣式優化 & 支援類型選擇) -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showCreateModal = false"></div>
        <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <FilePlus class="w-5 h-5 text-indigo-600" /> 建立盤點任務
                </h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
            </div>
            
            <div class="p-6 space-y-5">
                <!-- 盤點類型選擇 -->
                <div>
                    <label class="label">盤點類型</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="orderType = 'monthly'" :class="orderType === 'monthly' ? 'border-blue-500 bg-blue-50 text-blue-700 ring-2 ring-blue-200' : 'border-slate-200 text-slate-500 hover:bg-slate-50'" class="border-2 font-bold py-3 rounded-xl text-sm flex justify-center items-center gap-2 transition-all">
                            <CalendarClock class="w-4 h-4" /> 月度自盤
                        </button>
                        <button type="button" @click="orderType = 'annual'" :class="orderType === 'annual' ? 'border-purple-500 bg-purple-50 text-purple-700 ring-2 ring-purple-200' : 'border-slate-200 text-slate-500 hover:bg-slate-50'" class="border-2 font-bold py-3 rounded-xl text-sm flex justify-center items-center gap-2 transition-all">
                            <Flag class="w-4 h-4" /> 年度大盤
                        </button>
                    </div>
                </div>

                <div>
                    <label class="label">目標保管人 <span class="text-rose-500">*</span></label>
                    <select v-model="newOrderForm.responsible_user_id" class="input-field appearance-none bg-white">
                        <option disabled value="">請選擇...</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="label">資產分類 (選填)</label>
                    <select v-model="newOrderForm.category" class="input-field appearance-none bg-white">
                        <option value="">全部資產 (F1 + G1)</option>
                        <option value="F1">F1 固定資產</option>
                        <option value="G1">G1 列管資產</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button @click="showCreateModal = false" class="btn-secondary">取消</button>
                <button @click="createWorkOrder" class="btn-primary">確認生成</button>
            </div>
        </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { 
    ClipboardCheck, PlusCircle, Search, Loader2, Inbox, Flag, CalendarClock, 
    ScanLine, FilePlus, X 
} from 'lucide-vue-next';

const authStore = useAuthStore();
const workOrders = ref([]);
const users = ref([]);
const loading = ref(false);
const showCreateModal = ref(false);
const searchQuery = ref('');
const filterStatus = ref('全部'); 
const orderType = ref('monthly'); // 預設月度
const newOrderForm = reactive({ responsible_user_id: '', category: '' });

// --- Helper Functions ---

const isAnnualAudit = (title) => {
    return title && (title.includes('年度') || title.includes('年終') || title.includes('復盤'));
};

const calculateProgress = (order) => {
    if (!order.items_count) return 0;
    const scanned = order.scanned_count || 0; // 使用後端回傳的 scanned_count
    return Math.round((scanned / order.items_count) * 100);
};

const getProgressColor = (percent) => {
    if (percent >= 100) return 'bg-emerald-500';
    if (percent > 50) return 'bg-blue-500';
    return 'bg-amber-500';
};

const statusLabel = (status) => {
    switch(status) {
        case 'pending': return '未開始';
        case 'in_progress': return '進行中';
        case 'completed': return '已完成';
        default: return status;
    }
};

const statusBadgeClass = (status) => {
    switch(status) {
        case 'completed': return 'px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 border border-emerald-200';
        case 'in_progress': return 'px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 border border-blue-200';
        default: return 'px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200';
    }
};

// ★★★ 關鍵修正：修正日期計算邏輯 ★★★
const getTimeAgo = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();

    // 將時間設為當天凌晨，只比較日期
    const datePart = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const nowPart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    // 計算相差天數
    const diffTime = nowPart - datePart;
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return '今天';
    if (diffDays === 1) return '昨天';
    if (diffDays === 2) return '前天';
    return `${diffDays} 天前`;
};

// --- Computed ---
const filteredOrders = computed(() => {
    let result = workOrders.value;

    // 1. 狀態過濾
    if (filterStatus.value === '進行中') {
        result = result.filter(o => o.status === 'in_progress' || o.status === 'pending');
    } else if (filterStatus.value === '已完成') {
        result = result.filter(o => o.status === 'completed');
    }

    // 2. 關鍵字搜尋
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(o => 
            o.title.toLowerCase().includes(q) || 
            o.responsible_user?.name?.toLowerCase().includes(q) ||
            o.id.toString().includes(q)
        );
    }
    return result;
});

const stats = computed(() => {
    const total = workOrders.value.length;
    const inProgress = workOrders.value.filter(o => o.status === 'in_progress').length;
    const completed = workOrders.value.filter(o => o.status === 'completed').length;
    
    // 計算待盤點總數：總項目 - 總已盤
    const totalItems = workOrders.value.reduce((acc, o) => acc + (o.items_count || 0), 0);
    const scannedItems = workOrders.value.reduce((acc, o) => acc + (o.scanned_count || 0), 0);
    const pendingItems = totalItems - scannedItems;

    const rate = totalItems > 0 ? Math.round((scannedItems / totalItems) * 100) : 0;

    return {
        inProgress,
        totalUnscanned: pendingItems,
        completionRate: rate
    };
});

// --- API Actions ---

async function fetchWorkOrders() {
    loading.value = true;
    try {
        const res = await axios.get('/api/inventory/work-orders');
        workOrders.value = res.data.data;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function fetchUsers() {
    if (authStore.canCreateOrder) {
        try {
            const res = await axios.get('/api/users');
            users.value = res.data;
        } catch (error) { console.error(error); }
    }
}

async function createWorkOrder() {
    try {
        // 將 order_type 傳給後端，讓後端決定標題
        const payload = {
            ...newOrderForm,
            order_type: orderType.value 
        };
        
        await axios.post('/api/inventory/work-orders', payload);
        
        showCreateModal.value = false;
        newOrderForm.responsible_user_id = '';
        fetchWorkOrders();
    } catch (error) {
        alert(error.response?.data?.message || '生成失敗');
    }
}

onMounted(() => {
    fetchWorkOrders();
    fetchUsers();
});
</script>

<style scoped>
.stat-card { @apply bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition-all; }
.btn-primary { @apply px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all; }
.btn-secondary { @apply px-4 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition-all; }
.input-field { @apply w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-sm; }
.label { @apply block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide; }
</style>