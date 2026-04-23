<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 space-y-8 bg-slate-50 min-h-screen">
    <!-- 1. 頂部標題區 -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
          <div class="p-2 bg-indigo-600 rounded-lg shadow-lg shadow-indigo-200">
            <Activity class="w-6 h-6 text-white" />
          </div>
          校驗紀錄查詢
        </h1>
        <p class="text-slate-500 text-sm mt-2 ml-1">
          檢視與搜尋機台的校正測量結果與前置點檢紀錄
        </p>
      </div>
    </div>

    <!-- 2. 統計儀表板 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- 卡片 A: 總校驗次數 -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-indigo-300 transition-all">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">系統校驗總紀錄數</p>
          <div class="flex items-baseline gap-2">
            <h3 class="text-4xl font-black text-slate-800">{{ pagination.total }}</h3>
            <span class="text-sm text-slate-500 font-medium">筆</span>
          </div>
        </div>
        <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center group-hover:bg-indigo-100 transition-colors">
          <Database class="w-6 h-6 text-indigo-400 group-hover:text-indigo-600" />
        </div>
      </div>

      <!-- 卡片 B: 本頁測試結果 (視覺化) -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-emerald-300 transition-all">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">本頁測試結果分佈</p>
          <div class="flex items-center gap-3 mt-1">
            <div class="flex flex-col">
              <span class="text-2xl font-bold text-emerald-600">{{ pageStats.pass }}</span>
              <span class="text-[10px] text-slate-400">OK / PASS</span>
            </div>
            <div class="w-px h-8 bg-slate-200"></div>
            <div class="flex flex-col">
              <span class="text-2xl font-bold text-rose-500">{{ pageStats.fail }}</span>
              <span class="text-[10px] text-slate-400">NG / FAIL</span>
            </div>
          </div>
        </div>
        <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center">
          <CheckCircle class="w-6 h-6 text-emerald-600" />
        </div>
      </div>

      <!-- 卡片 C: 人員分佈 -->
      <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-blue-300 transition-all">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">本頁校驗人員數</p>
          <div class="flex items-baseline gap-2 mt-1">
             <h3 class="text-4xl font-black text-slate-800">{{ pageStats.uniqueUsers }}</h3>
             <span class="text-sm text-slate-500 font-medium">人</span>
          </div>
        </div>
        <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center">
          <Users class="w-6 h-6 text-blue-600" />
        </div>
      </div>
    </div>

    <!-- 3. 過濾搜尋區 -->
    <div class="bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row gap-2">
      <div class="relative flex-1 group">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
          <Search class="w-5 h-5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
        </div>
        <input
          v-model="searchQuery"
          @input="debounceSearch"
          type="text"
          class="block w-full pl-11 pr-4 py-3.5 bg-transparent border-none rounded-xl text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 transition-all hover:bg-slate-50 focus:bg-white"
          placeholder="搜尋機台資產編號、通道 ID (如 A0013) 或 人員工號..."
        />
        <button
          v-if="searchQuery"
          @click="clearSearch"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
        >
          <XCircle class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- 4. 資料清單表格 -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
      <div v-if="loading" class="p-12 flex justify-center items-center text-indigo-600">
        <Loader2 class="w-8 h-8 animate-spin" />
        <span class="ml-3 font-medium">資料載入中...</span>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-sm whitespace-nowrap">
          <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
            <tr>
              <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">測試時間</th>
              <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">機台編號</th>
              <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">通道資訊</th>
              <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">人員工號</th>
              <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs">測試結果</th>
              <th class="px-6 py-4 font-bold uppercase tracking-wider text-xs text-right">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="records.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-slate-400 bg-slate-50/50">
                <div class="flex flex-col items-center justify-center">
                  <Inbox class="w-12 h-12 mb-3 opacity-30" />
                  <p>沒有找到符合條件的校驗紀錄</p>
                </div>
              </td>
            </tr>
            <tr v-for="record in records" :key="record.id" class="group hover:bg-indigo-50/30 transition-colors">
              <td class="px-6 py-4">
                <div class="font-bold text-slate-900">{{ formatDate(record.start_time) }}</div>
                <div class="text-xs text-slate-500 mt-0.5">至 {{ formatDate(record.end_time) }}</div>
              </td>
              <td class="px-6 py-4 text-slate-700 font-mono font-bold">
                 {{ record.asset_number }}
              </td>
              <td class="px-6 py-4">
                 <div class="font-bold text-slate-800">{{ record.channel_id }}</div>
              </td>
              <td class="px-6 py-4 font-medium text-slate-700">
                <div class="flex items-center gap-1.5 text-slate-600">
                    <UserCircle class="w-4 h-4 text-indigo-400" />
                    {{ record.employee_id }}
                </div>
              </td>
              <td class="px-6 py-4">
                <span :class="(record.final_result === 'PASS' || record.final_result === 'OK') ? 'bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-bold' : 'bg-rose-100 text-rose-700 px-2.5 py-1 rounded-full text-xs font-bold'">
                  {{ record.final_result }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  @click="openDetailModal(record)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 border border-indigo-200 rounded-lg transition-colors bg-white shadow-sm"
                >
                  <Eye class="w-4 h-4" /> 檢視明細
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁控制器 -->
      <div class="px-6 py-4 bg-white border-t border-slate-200 flex items-center justify-between">
        <div class="text-sm text-slate-500">
          顯示 <span class="font-bold text-slate-700">{{ pagination.from }}</span> 到 <span class="font-bold text-slate-700">{{ pagination.to }}</span> 筆，共 <span class="font-bold text-slate-700">{{ pagination.total }}</span> 筆紀錄
        </div>
        <div class="flex items-center gap-2">
          <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 transition-colors">
            <ChevronLeft class="w-5 h-5 text-slate-600" />
          </button>
          <span class="text-sm font-medium text-slate-600 px-2">Page {{ pagination.current_page }}</span>
          <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 transition-colors">
            <ChevronRight class="w-5 h-5 text-slate-600" />
          </button>
        </div>
      </div>
    </div>

    <!-- 5. 檢視明細 Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeDetailModal"></div>
      
      <div class="relative bg-white w-full max-w-5xl max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col transform transition-all">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between shrink-0">
          <div>
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                  <Activity class="w-5 h-5 text-indigo-600" />
              </div>
              校驗明細報告
            </h2>
            <div class="flex gap-4 text-xs text-slate-500 mt-1">
               <span>機台編號: <b class="text-slate-700">{{ selectedRecord.asset_number }}</b></span>
               <span>通道: <b class="text-slate-700">{{ selectedRecord.channel_id }}</b></span>
               <span>人員: <b class="text-slate-700">{{ selectedRecord.employee_id }}</b></span>
            </div>
          </div>
          <button @click="closeDetailModal" class="text-slate-400 hover:text-slate-700 transition-colors p-2 bg-white rounded-lg border border-slate-200 shadow-sm">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto bg-slate-50 space-y-8 flex-1">
          
          <!-- 前置點檢區塊 -->
          <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
             <div class="px-5 py-3 border-b border-slate-100 bg-blue-50/50 flex items-center gap-2">
                <FileSearch class="w-5 h-5 text-blue-500" />
                <h3 class="font-bold text-slate-800">前置點檢明細 (Pre-Verify)</h3>
             </div>
             <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-left whitespace-nowrap">
                   <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                      <tr>
                         <th class="px-4 py-2 font-bold uppercase">類型</th>
                         <th class="px-4 py-2 font-bold uppercase">Range</th>
                         <th class="px-4 py-2 font-bold uppercase">測試項目</th>
                         <th class="px-4 py-2 font-bold uppercase">Standard Val</th>
                         <th class="px-4 py-2 font-bold uppercase">Meter Val</th>
                         <th class="px-4 py-2 font-bold uppercase">ADC Val</th>
                         <th class="px-4 py-2 font-bold uppercase">差異(Diff)</th>
                         <th class="px-4 py-2 font-bold uppercase">結果</th>
                      </tr>
                   </thead>
                   <tbody class="divide-y divide-slate-100">
                      <tr v-if="!selectedRecord.pre_verify_details?.length">
                          <td colspan="8" class="px-4 py-6 text-center text-slate-400">無前置點檢資料</td>
                      </tr>
                      <tr v-for="td in selectedRecord.pre_verify_details" :key="td.id" class="hover:bg-slate-50">
                         <td class="px-4 py-2">{{ td.calibration_type || '-' }}</td>
                         <td class="px-4 py-2 text-slate-500 font-mono">{{ td.range_info || '-' }}</td>
                         <td class="px-4 py-2 font-medium">{{ td.test_item }}</td>
                         <td class="px-4 py-2 font-mono text-slate-600">{{ td.standard_value }}</td>
                         <td class="px-4 py-2 font-mono text-slate-600">{{ td.meter_value }}</td>
                         <td class="px-4 py-2 font-mono text-slate-600">{{ td.adc_value }}</td>
                         <td class="px-4 py-2 font-mono text-indigo-600">{{ td.difference }}</td>
                         <td class="px-4 py-2">
                             <span :class="(td.status === 'PASS' || td.status === 'OK') ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'">{{ td.status }}</span>
                         </td>
                      </tr>
                   </tbody>
                </table>
             </div>
          </div>

          <!-- 校正量測明細區塊 -->
          <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
             <div class="px-5 py-3 border-b border-slate-100 bg-emerald-50/50 flex items-center gap-2">
                <Activity class="w-5 h-5 text-emerald-500" />
                <h3 class="font-bold text-slate-800">校正量測明細 (Calibration)</h3>
             </div>
             <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-left whitespace-nowrap">
                   <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                      <tr>
                         <th class="px-4 py-2 font-bold uppercase">類型</th>
                         <th class="px-4 py-2 font-bold uppercase">Range</th>
                         <th class="px-4 py-2 font-bold uppercase">測試項目</th>
                         <th class="px-4 py-2 font-bold uppercase">Standard Val</th>
                         <th class="px-4 py-2 font-bold uppercase">Meter Val</th>
                         <th class="px-4 py-2 font-bold uppercase">ADC Val</th>
                         <th class="px-4 py-2 font-bold uppercase">差異(Diff)</th>
                         <th class="px-4 py-2 font-bold uppercase">結果</th>
                      </tr>
                   </thead>
                   <tbody class="divide-y divide-slate-100">
                      <tr v-if="!selectedRecord.details?.length">
                          <td colspan="8" class="px-4 py-6 text-center text-slate-400">無校正量測資料</td>
                      </tr>
                      <tr v-for="td in selectedRecord.details" :key="td.id" class="hover:bg-slate-50">
                         <td class="px-4 py-2">{{ td.calibration_type || '-' }}</td>
                         <td class="px-4 py-2 text-slate-500 font-mono">{{ td.range_info || '-' }}</td>
                         <td class="px-4 py-2 font-medium">{{ td.test_item }}</td>
                         <td class="px-4 py-2 font-mono text-slate-600">{{ td.standard_value }}</td>
                         <td class="px-4 py-2 font-mono text-slate-600">{{ td.meter_value }}</td>
                         <td class="px-4 py-2 font-mono text-slate-600">{{ td.adc_value }}</td>
                         <td class="px-4 py-2 font-mono text-indigo-600">{{ td.difference }}</td>
                         <td class="px-4 py-2">
                             <span :class="(td.status === 'PASS' || td.status === 'OK') ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'">{{ td.status }}</span>
                         </td>
                      </tr>
                   </tbody>
                </table>
             </div>
          </div>
          
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-white border-t border-slate-100 flex justify-end shrink-0">
          <button @click="closeDetailModal" class="px-6 py-2.5 text-sm font-bold text-white bg-slate-800 hover:bg-slate-900 rounded-xl transition-all shadow-md">
            關閉視窗
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import axios from "axios";
import {
  Activity, Database, Search, Users, Inbox, XCircle, 
  ChevronLeft, ChevronRight, Loader2, CheckCircle,
  Eye, Monitor, UserCircle, X, FileSearch
} from "lucide-vue-next";

// 狀態管理
const loading = ref(false);
const records = ref([]);
const searchQuery = ref("");
const showDetailModal = ref(false);
const selectedRecord = ref({});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  from: 0,
  to: 0,
});

// 當前頁面統計儀表板防抖動計算
const pageStats = computed(() => {
  let passCount = 0;
  let failCount = 0;
  const userSet = new Set();
  
  for (const r of records.value) {
     if(r.final_result === 'PASS' || r.final_result === 'OK') passCount++;
     else if(r.final_result === 'FAIL' || r.final_result === 'NG') failCount++;
     
     if(r.employee_id) userSet.add(r.employee_id);
  }
  
  return {
      pass: passCount,
      fail: failCount,
      uniqueUsers: userSet.size
  };
});

// 防抖動搜尋 (Debounce)
let timeout = null;
const debounceSearch = () => {
  clearTimeout(timeout);
  timeout = setTimeout(() => fetchRecords(1), 300);
};

const clearSearch = () => {
  searchQuery.value = "";
  fetchRecords(1);
};

// 格式化日期時間
const formatDate = (dateString) => {
    if (!dateString) return '-';
    // dateString 型態像是 "2026-03-31 10:00:00" 取除秒數
    return dateString.substring(0, 16); 
};

// 獲取API資料
const fetchRecords = async (page = 1) => {
  loading.value = true;
  try {
    const res = await axios.get("/api/calibrations", {
      params: { search: searchQuery.value, page: page },
    });
    
    // 將資料配置入狀態
    records.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.total = res.data.total;
    pagination.from = res.data.from;
    pagination.to = res.data.to;
  } catch (err) {
    console.error("無法取得校驗紀錄", err);
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.last_page) fetchRecords(page);
};

// Modal 開關邏輯
const openDetailModal = (record) => {
   selectedRecord.value = record;
   showDetailModal.value = true;
   // 鎖定背景捲動
   document.body.style.overflow = "hidden";
};

const closeDetailModal = () => {
   showDetailModal.value = false;
   selectedRecord.value = {};
   // 恢復背景捲動
   document.body.style.overflow = "auto";
};

// 初始化
onMounted(() => {
  fetchRecords();
});
</script>

<style scoped>
/* 隱藏原生捲軸但保留捲動功能 */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 20px;
}
</style>
