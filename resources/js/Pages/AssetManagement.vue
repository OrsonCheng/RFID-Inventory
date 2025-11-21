<template>
  <div class="px-4 sm:px-0 space-y-6">
    
    <!-- 1. 頁面標題與頂部操作區 -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
          <Package class="w-6 h-6 text-indigo-600" /> 資產管理清單
        </h1>
        <p class="text-slate-500 text-sm mt-1">管理所有固定資產及其 RFID 綁定狀態</p>
      </div>
      <div class="flex gap-3">
        <button @click="openAddModal" class="btn-primary shadow-lg shadow-indigo-100">
          <Plus class="w-4 h-4" /> 新增資產
        </button>
        <button class="btn-secondary" title="匯出功能(開發中)">
          <Download class="w-4 h-4" /> 匯出列表
        </button>
      </div>
    </div>

    <!-- 2. 統計摘要卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- 這裡的數字我們暫時用 pagination.total (真實總數) 來顯示，而不是只抓當前頁面的數量 -->
      <div class="stat-card border-l-4 border-indigo-500">
        <div class="text-slate-500 text-xs font-medium uppercase">資產總數</div>
        <div class="text-2xl font-bold text-slate-800 mt-1">{{ pagination.total }}</div>
      </div>
      <!-- 以下兩個數字因為後端沒回傳統計，暫時只能顯示當前頁面的統計，或需要另外一支 API -->
      <div class="stat-card border-l-4 border-emerald-500">
        <div class="text-slate-500 text-xs font-medium uppercase">本頁已綁定</div>
        <div class="text-2xl font-bold text-emerald-700 mt-1">
            {{ assets.filter(a => a.rfid_tag).length }}
        </div>
      </div>
      <div class="stat-card border-l-4 border-amber-500">
        <div class="text-slate-500 text-xs font-medium uppercase">本頁未綁定</div>
        <div class="text-2xl font-bold text-amber-700 mt-1">
            {{ assets.filter(a => !a.rfid_tag).length }}
        </div>
      </div>
    </div>

    <!-- 3. 搜尋與過濾區塊 -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col md:flex-row gap-4">
      <!-- 關鍵字搜尋 -->
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5" />
        <input 
          v-model="searchQuery" 
          @input="() => fetchAssets(1)" 
          type="text" 
          placeholder="輸入資產名稱、固資編號或 RFID 進行搜尋..." 
          class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
        >
      </div>

      <!-- 保管人篩選 -->
      <div class="w-full md:w-64 relative">
        <User class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5" />
        <select 
            v-model="filterUser" 
            @change="() => fetchAssets(1)"
            class="w-full pl-10 pr-8 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none appearance-none bg-white text-slate-700 cursor-pointer"
        >
            <option value="">所有保管人</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4 pointer-events-none" />
      </div>
    </div>

    <!-- 4. 專業數據表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs font-bold uppercase tracking-wider">
              <th class="p-4 w-32">固資編號</th>
              <th class="p-4">資產資訊 (名稱/位置)</th>
              <th class="p-4 w-40">RFID 標籤</th>
              <th class="p-4 w-40">保管人</th>
              <th class="p-4 w-32">狀態</th>
              <th class="p-4 w-20 text-right">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading" class="animate-pulse">
                <td colspan="6" class="p-8 text-center text-slate-400">資料載入中...</td>
            </tr>
            <tr v-else-if="assets.length === 0">
                <td colspan="6" class="p-12 text-center">
                    <div class="flex flex-col items-center justify-center text-slate-400">
                        <Inbox class="w-12 h-12 mb-3 opacity-50" />
                        <p>找不到符合的資產資料</p>
                    </div>
                </td>
            </tr>
            <tr 
                v-for="asset in assets" 
                :key="asset.id" 
                class="hover:bg-slate-50 transition-colors group"
            >
              <td class="p-4 font-mono text-slate-600 font-medium">{{ asset.asset_tag }}</td>
              <td class="p-4">
                <div class="font-bold text-slate-800 text-sm">{{ asset.name }}</div>
                <div class="flex items-center gap-1 text-xs text-slate-400 mt-1">
                    <MapPin class="w-3 h-3" />
                    {{ asset.location || '未指定位置' }}
                </div>
              </td>
              <td class="p-4">
                <span v-if="asset.rfid_tag" class="badge-success flex items-center gap-1.5 w-fit">
                  <Wifi class="w-3 h-3" /> {{ asset.rfid_tag }}
                </span>
                <span v-else class="badge-neutral flex items-center gap-1.5 w-fit">
                  <WifiOff class="w-3 h-3" /> 未綁定
                </span>
              </td>
              <td class="p-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-bold border border-indigo-100">
                        {{ asset.responsible_user?.name?.charAt(0) || '?' }}
                    </div>
                    <span class="text-sm text-slate-600">{{ asset.responsible_user?.name || '無' }}</span>
                </div>
              </td>
              <td class="p-4">
                <span :class="statusBadgeClass(asset.status)">
                    {{ asset.status }}
                </span>
              </td>
              <td class="p-4 text-right">
                <button @click="editAsset(asset)" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-full transition-all" title="編輯/綁定">
                  <Edit3 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ★★★ 分頁控制器 (New) ★★★ -->
      <div class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between sm:px-6">
        <!-- 手機版簡易分頁 -->
        <div class="flex 1 justify-between sm:hidden w-full">
            <button 
                @click="changePage(pagination.current_page - 1)" 
                :disabled="pagination.current_page <= 1"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
                上一頁
            </button>
            <button 
                @click="changePage(pagination.current_page + 1)" 
                :disabled="pagination.current_page >= pagination.last_page"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
                下一頁
            </button>
        </div>

        <!-- 電腦版完整分頁 -->
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-500">
                    顯示第 <span class="font-medium text-slate-900">{{ pagination.from }}</span>
                    到 <span class="font-medium text-slate-900">{{ pagination.to }}</span> 筆，
                    共 <span class="font-medium text-slate-900">{{ pagination.total }}</span> 筆
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button 
                        @click="changePage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="sr-only">Previous</span>
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    
                    <!-- 頁碼顯示 (簡易版：只顯示當前頁) -->
                    <span class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700">
                        第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁
                    </span>

                    <button 
                        @click="changePage(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="sr-only">Next</span>
                        <ChevronRight class="h-5 w-5" />
                    </button>
                </nav>
            </div>
        </div>
      </div>
    </div>

    <!-- 5. 新增/編輯 Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- ... Modal 內容保持不變 ... -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
        <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl p-6 transform transition-all scale-100">
            <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold text-slate-800">
                    {{ isEditMode ? '編輯資產 / 綁定 RFID' : '新增固定資產' }}
                </h2>
                <button @click="closeModal" class="text-slate-400 hover:text-slate-600">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="label">固資編號 <span class="text-rose-500">*</span></label>
                        <input v-model="form.asset_tag" type="text" class="input-field" placeholder="例如: F-2025001" :disabled="isEditMode">
                    </div>
                    <div class="col-span-2">
                        <label class="label">設備名稱 <span class="text-rose-500">*</span></label>
                        <input v-model="form.name" type="text" class="input-field" placeholder="例如: 示波器">
                    </div>
                    <div class="col-span-2">
                        <label class="label flex items-center gap-2">
                            <ScanLine class="w-3 h-3 text-indigo-500" /> RFID 標籤 ID
                        </label>
                        <input v-model="form.rfid_tag" type="text" class="input-field bg-indigo-50/50 border-indigo-200 focus:ring-indigo-200" placeholder="請使用掃描槍輸入...">
                    </div>
                    <div>
                        <label class="label">保管人 <span class="text-rose-500">*</span></label>
                        <select v-model="form.responsible_user_id" class="input-field appearance-none">
                            <option disabled value="">請選擇人員</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">目前狀態</label>
                        <select v-model="form.status" class="input-field appearance-none">
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

            <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button @click="closeModal" class="btn-secondary">取消</button>
                <button @click="save" class="btn-primary px-6">儲存變更</button>
            </div>
        </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import axios from 'axios';
import { 
    Package, Plus, Download, Search, Filter, Wifi, WifiOff, 
    Edit3, Inbox, MapPin, ScanLine, X, User, ChevronDown, 
    ChevronLeft, ChevronRight // 新增分頁圖標
} from 'lucide-vue-next';

// State
const assets = ref([]);
const users = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const isEditMode = ref(false);
const searchQuery = ref('');
const filterUser = ref('');

// ★★★ 分頁狀態 ★★★
const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
    per_page: 50
});

// Form Data
const defaultForm = { 
    id: null,
    asset_tag: '', 
    name: '', 
    rfid_tag: '', 
    responsible_user_id: '', 
    status: '使用中',
    location: ''
};
const form = reactive({ ...defaultForm });

// API Calls
// ★★★ 修改 fetchAssets 支援 page 參數 ★★★
const fetchAssets = async (page = 1) => { 
    loading.value = true;
    try {
        const res = await axios.get('/api/assets', { 
            params: { 
                search: searchQuery.value,
                user_id: filterUser.value,
                page: page // 傳遞頁碼給後端
            } 
        }); 
        
        assets.value = res.data.data; 
        
        // 更新分頁資訊 (Laravel 預設格式)
        pagination.current_page = res.data.current_page;
        pagination.last_page = res.data.last_page;
        pagination.total = res.data.total;
        pagination.from = res.data.from;
        pagination.to = res.data.to;
        pagination.per_page = res.data.per_page;

    } catch(e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.last_page) {
        fetchAssets(page);
    }
};

const fetchUsers = async () => {
    const res = await axios.get('/api/users');
    users.value = res.data;
};

// Modal Actions
const openAddModal = () => {
    isEditMode.value = false;
    Object.assign(form, defaultForm);
    showAddModal.value = true;
};

const editAsset = (asset) => {
    isEditMode.value = true;
    Object.assign(form, {
        id: asset.id,
        asset_tag: asset.asset_tag,
        name: asset.name,
        rfid_tag: asset.rfid_tag,
        responsible_user_id: asset.responsible_user_id,
        status: asset.status,
        location: asset.location
    });
    showAddModal.value = true;
};

const closeModal = () => {
    showAddModal.value = false;
    setTimeout(() => Object.assign(form, defaultForm), 200);
};

const save = async () => {
    try {
        if (!form.asset_tag || !form.name || !form.responsible_user_id) {
            alert('請填寫所有必填欄位 (*)');
            return;
        }

        if (isEditMode.value) {
            await axios.put(`/api/assets/${form.id}`, form);
        } else {
            await axios.post('/api/assets', form);
        }
        
        // 儲存後重新整理當前頁面
        await fetchAssets(pagination.current_page); 
        closeModal();
    } catch(e) {
        const msg = e.response?.data?.message || '儲存失敗，請檢查固資編號是否重複';
        alert(msg);
    }
};

const statusBadgeClass = (status) => {
  switch(status) {
    case '使用中': return 'badge-blue';
    case '閒置': return 'badge-yellow';
    case '維修中': return 'badge-red';
    case '已報廢': return 'badge-neutral text-slate-400 decoration-line-through';
    default: return 'badge-neutral';
  }
};

onMounted(() => {
    fetchAssets();
    fetchUsers();
});
</script>

<style scoped>
.btn-primary { @apply bg-indigo-600 text-white px-4 py-2.5 rounded-lg hover:bg-indigo-700 transition-all flex items-center gap-2 font-medium active:scale-95; }
.btn-secondary { @apply bg-white text-slate-600 border border-slate-200 px-4 py-2.5 rounded-lg hover:bg-slate-50 hover:text-slate-800 transition-colors flex items-center gap-2 font-medium; }
.stat-card { @apply bg-white p-5 rounded-xl shadow-sm border-slate-200; }
.input-field { @apply w-full p-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all text-slate-700 text-sm; }
.label { @apply block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide; }
.badge-success { @apply px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100; }
.badge-blue    { @apply px-2.5 py-1 rounded-md text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100; }
.badge-yellow  { @apply px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-600 border border-amber-100; }
.badge-red     { @apply px-2.5 py-1 rounded-md text-xs font-bold bg-rose-50 text-rose-600 border border-rose-100; }
.badge-neutral { @apply px-2.5 py-1 rounded-md text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200; }
</style>