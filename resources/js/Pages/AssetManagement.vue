<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 space-y-8 bg-slate-50 min-h-screen">
    
    <!-- 1. 頂部導航與操作區 -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
          <div class="p-2 bg-indigo-600 rounded-lg shadow-lg shadow-indigo-200">
            <Package class="w-6 h-6 text-white" />
          </div>
          資產管理清單
        </h1>
        <p class="text-slate-500 text-sm mt-2 ml-1">管理公司固定資產、RFID 綁定與狀態追蹤</p>
      </div>
      
      <!-- 頂部按鈕群 -->
      <div class="flex gap-3" v-if="authStore.canManageAssets">
        <button 
            @click="showImportModal = true"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-300 text-slate-700 font-medium rounded-xl hover:bg-slate-50 hover:border-slate-400 transition-all shadow-sm"
        >
          <UploadCloud class="w-4 h-4 text-slate-500" />
          <span>批量導入</span>
        </button>
        <button 
            @click="openAddModal"
            class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 hover:shadow-indigo-200 hover:shadow-lg transition-all shadow-md active:scale-95"
        >
          <Plus class="w-5 h-5" />
          <span>新增資產</span>
        </button>
      </div>
    </div>

    <!-- 2. 關鍵指標儀表板 (Metrics Dashboard) -->
    <!-- 改用 pagination.total 來顯示真實總數，而非僅顯示當前頁面數量 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- 卡片 A: 資產總數 -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-indigo-300 transition-all">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">系統資產總數</p>
          <div class="flex items-baseline gap-2">
            <h3 class="text-4xl font-black text-slate-800">{{ pagination.total }}</h3>
            <span class="text-sm text-slate-500 font-medium">筆</span>
          </div>
        </div>
        <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center group-hover:bg-indigo-50 transition-colors">
          <Database class="w-6 h-6 text-slate-400 group-hover:text-indigo-600" />
        </div>
      </div>

      <!-- 卡片 B: 本頁綁定率 (視覺化) -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-emerald-300 transition-all">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">本頁 RFID 綁定狀態</p>
            <div class="flex items-center gap-3 mt-1">
                <div class="flex flex-col">
                    <span class="text-2xl font-bold text-emerald-600">{{ rfidStats.bound }}</span>
                    <span class="text-[10px] text-slate-400">已綁定</span>
                </div>
                <div class="w-px h-8 bg-slate-200"></div>
                <div class="flex flex-col">
                    <span class="text-2xl font-bold text-amber-500">{{ rfidStats.unbound }}</span>
                    <span class="text-[10px] text-slate-400">未綁定</span>
                </div>
            </div>
        </div>
        <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center">
          <Wifi class="w-6 h-6 text-emerald-600" />
        </div>
      </div>

      <!-- 卡片 C: 保管人統計 (本頁) -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-blue-300 transition-all">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">保管人分布 (本頁)</p>
          <div class="flex -space-x-2 overflow-hidden py-1">
             <!-- 顯示前 5 位保管人的頭像 -->
             <template v-for="(user, index) in uniqueOwners" :key="index">
                <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600" :title="user">
                    {{ user.charAt(0) }}
                </div>
             </template>
             <div v-if="uniqueOwners.length > 5" class="inline-block h-8 w-8 rounded-full ring-2 ring-white bg-slate-100 flex items-center justify-center text-xs font-medium text-slate-500">
                +{{ uniqueOwners.length - 5 }}
             </div>
          </div>
        </div>
        <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center">
          <Users class="w-6 h-6 text-blue-600" />
        </div>
      </div>
    </div>

    <!-- 3. 高級過濾器 (Filter Bar) -->
    <div class="bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row gap-2">
      
      <!-- 搜尋框 -->
      <div class="relative flex-1 group">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
          <Search class="w-5 h-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
        </div>
        <input 
          v-model="searchQuery" 
          @input="debounceSearch" 
          type="text" 
          class="block w-full pl-11 pr-4 py-3.5 bg-transparent border-none rounded-xl text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 transition-all hover:bg-slate-50 focus:bg-white" 
          placeholder="搜尋資產編號、設備名稱、RFID Tag 或 型號..."
        >
        <!-- 清除按鈕 -->
        <button v-if="searchQuery" @click="clearSearch" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
            <XCircle class="w-5 h-5" />
        </button>
      </div>

      <div class="w-px bg-slate-200 my-2 hidden md:block"></div>

      <!-- 保管人下拉選單 -->
      <div class="relative md:w-72 group">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <User class="w-5 h-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
        </div>
        <select 
            v-model="filterUser" 
            @change="() => fetchAssets(1)"
            class="block w-full pl-10 pr-10 py-3.5 bg-transparent border-none rounded-xl text-slate-900 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 cursor-pointer hover:bg-slate-50 focus:bg-white appearance-none transition-all"
        >
            <option value="">所有保管人 (All Users)</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
            <ChevronDown class="w-4 h-4 text-slate-400" />
        </div>
      </div>
    </div>

    <!-- 4. 資產列表 (Data Table) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
        <!-- 表格讀取遮罩 -->
        <div v-if="loading" class="p-12 flex justify-center items-center text-indigo-600">
            <Loader2 class="w-8 h-8 animate-spin" />
            <span class="ml-3 font-medium">資料載入中...</span>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">資產編號 / 圖片</th>
                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">設備資訊</th>
                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">RFID 綁定</th>
                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">保管人 / 位置</th>
                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">狀態</th>
                        <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="assets.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 bg-slate-50/50">
                            <div class="flex flex-col items-center justify-center">
                                <Inbox class="w-12 h-12 mb-3 opacity-30" />
                                <p>沒有找到符合條件的資產</p>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="asset in assets" :key="asset.id" class="group hover:bg-indigo-50/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-4">
                                <!-- 資產圖示 (未來可換成照片) -->
                                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 border border-slate-200">
                                    <Box class="w-5 h-5" />
                                </div>
                                <div class="font-mono font-bold text-slate-700 group-hover:text-indigo-600 transition-colors">
                                    {{ asset.asset_tag }}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-900">{{ asset.name }}</div>
                            <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-2">
                                <span v-if="asset.brand" class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">{{ asset.brand }}</span>
                                <span v-if="asset.model">{{ asset.model }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span v-if="asset.rfid_tag" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                <Wifi class="w-3.5 h-3.5" /> {{ asset.rfid_tag }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200 opacity-60">
                                <WifiOff class="w-3.5 h-3.5" /> 未綁定
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2 text-slate-700 font-medium">
                                    <UserCircle class="w-4 h-4 text-slate-400" />
                                    {{ asset.responsible_user?.name || '未分配' }}
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500">
                                    <MapPin class="w-3.5 h-3.5 text-slate-400" />
                                    {{ asset.location || '位置未詳' }}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="statusBadgeClass(asset.status)">
                                {{ asset.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button v-if="authStore.canManageAssets" @click="editAsset(asset)" 
                                class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" 
                                title="編輯資料"
                            >
                                <Edit3 class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 分頁控制器 -->
        <div class="px-6 py-4 bg-white border-t border-slate-200 flex items-center justify-between">
            <div class="text-sm text-slate-500">
                顯示 <span class="font-bold text-slate-700">{{ pagination.from }}</span> 到 <span class="font-bold text-slate-700">{{ pagination.to }}</span> 筆，共 <span class="font-bold text-slate-700">{{ pagination.total }}</span> 筆資料
            </div>
            <div class="flex items-center gap-2">
                <button 
                    @click="changePage(pagination.current_page - 1)"
                    :disabled="pagination.current_page <= 1"
                    class="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <ChevronLeft class="w-5 h-5 text-slate-600" />
                </button>
                <span class="text-sm font-medium text-slate-600 px-2">Page {{ pagination.current_page }}</span>
                <button 
                    @click="changePage(pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page"
                    class="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <ChevronRight class="w-5 h-5 text-slate-600" />
                </button>
            </div>
        </div>
    </div>

    <!-- 新增/編輯 Modal (樣式已優化) -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
        <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden transform transition-all">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-1 h-6 bg-indigo-500 rounded-full"></span>
                    {{ isEditMode ? '編輯資產資料' : '新增固定資產' }}
                </h2>
                <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <X class="w-6 h-6" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-2 gap-5">
                    <div class="col-span-2">
                        <label class="label">固資編號 <span class="text-rose-500">*</span></label>
                        <input v-model="form.asset_tag" type="text" class="input-field font-mono" placeholder="F-2025001" :disabled="isEditMode">
                    </div>
                    <div class="col-span-2">
                        <label class="label">設備名稱 <span class="text-rose-500">*</span></label>
                        <input v-model="form.name" type="text" class="input-field" placeholder="例如: 示波器">
                    </div>
                    <div class="col-span-2 bg-indigo-50/50 p-4 rounded-xl border border-indigo-100">
                        <label class="label flex items-center gap-2 text-indigo-900">
                            <ScanLine class="w-4 h-4 text-indigo-600" /> RFID 標籤 ID
                        </label>
                        <input v-model="form.rfid_tag" type="text" class="input-field border-indigo-200 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="請使用掃描槍輸入...">
                    </div>
                    <div>
                        <label class="label">保管人 <span class="text-rose-500">*</span></label>
                        <select v-model="form.responsible_user_id" class="input-field appearance-none bg-white">
                            <option disabled value="">請選擇</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">狀態</label>
                        <select v-model="form.status" class="input-field appearance-none bg-white">
                            <option>使用中</option>
                            <option>閒置</option>
                            <option>維修中</option>
                            <option>已報廢</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="label">存放位置</label>
                        <input v-model="form.location" type="text" class="input-field" placeholder="例如: 三廠二樓-實驗室">
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button @click="closeModal" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-colors">取消</button>
                <button @click="save" class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-lg shadow-indigo-200 transition-all active:scale-95 flex items-center gap-2">
                    <Check class="w-4 h-4" /> 儲存變更
                </button>
            </div>
        </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import axios from 'axios';
import { 
    Package, Plus, UploadCloud, Search, Filter, Wifi, WifiOff, 
    Edit3, Inbox, MapPin, ScanLine, X, User, ChevronDown, 
    ChevronLeft, ChevronRight, Database, Users, Box, Check, Loader2, XCircle, UserCircle
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const assets = ref([]);
const users = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const isEditMode = ref(false);
const searchQuery = ref('');
const filterUser = ref('');

// 分頁狀態
const pagination = reactive({
    current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 50
});

// Form Data
const defaultForm = { id: null, asset_tag: '', name: '', rfid_tag: '', responsible_user_id: '', status: '使用中', location: '' };
const form = reactive({ ...defaultForm });

// 防抖動搜尋 (Debounce)
let timeout = null;
const debounceSearch = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => fetchAssets(1), 300);
};

const clearSearch = () => {
    searchQuery.value = '';
    fetchAssets(1);
};

// API Calls
const fetchAssets = async (page = 1) => { 
    loading.value = true;
    try {
        const res = await axios.get('/api/assets', { 
            params: { search: searchQuery.value, user_id: filterUser.value, page: page } 
        }); 
        assets.value = res.data.data; 
        
        pagination.current_page = res.data.current_page;
        pagination.last_page = res.data.last_page;
        pagination.total = res.data.total;
        pagination.from = res.data.from;
        pagination.to = res.data.to;
        pagination.per_page = res.data.per_page;

    } catch(e) { console.error(e); } finally { loading.value = false; }
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.last_page) fetchAssets(page);
};

const fetchUsers = async () => {
    if (authStore.canManageAssets) {
        try {
            const res = await axios.get('/api/users');
            users.value = res.data;
        } catch (e) {}
    }
};

// Computed Stats (僅針對當前頁面做簡單可視化，總數用 pagination.total)
const rfidStats = computed(() => {
    const bound = assets.value.filter(a => a.rfid_tag).length;
    return { bound, unbound: assets.value.length - bound };
});

const uniqueOwners = computed(() => {
    const names = assets.value.map(a => a.responsible_user?.name).filter(Boolean);
    return [...new Set(names)];
});

// Actions
const openAddModal = () => {
    isEditMode.value = false;
    Object.assign(form, defaultForm);
    showAddModal.value = true;
};

const editAsset = (asset) => {
    isEditMode.value = true;
    Object.assign(form, {
        id: asset.id, asset_tag: asset.asset_tag, name: asset.name,
        rfid_tag: asset.rfid_tag, responsible_user_id: asset.responsible_user_id,
        status: asset.status, location: asset.location
    });
    showAddModal.value = true;
};

const closeModal = () => {
    showAddModal.value = false;
    setTimeout(() => Object.assign(form, defaultForm), 200);
};

const save = async () => {
    try {
        if (!form.asset_tag || !form.name || !form.responsible_user_id) return alert('請填寫必填欄位');
        if (isEditMode.value) await axios.put(`/api/assets/${form.id}`, form);
        else await axios.post('/api/assets', form);
        
        await fetchAssets(pagination.current_page); 
        closeModal();
    } catch(e) {
        alert(e.response?.data?.message || '儲存失敗');
    }
};

const statusBadgeClass = (status) => {
  switch(status) {
    case '使用中': return 'px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700';
    case '閒置': return 'px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700';
    case '維修中': return 'px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700';
    default: return 'px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500';
  }
};

onMounted(() => {
    fetchAssets();
    fetchUsers();
});
</script>

<style scoped>
.input-field {
    @apply w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-sm text-slate-800 placeholder:text-slate-400;
}
.label {
    @apply block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider;
}
</style>