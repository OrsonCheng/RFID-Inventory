<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" v-if="order">
    
    <!-- 0. 網路狀態橫幅 -->
    <transition name="slide-down">
      <div v-if="!isOnline" class="fixed top-0 left-0 w-full bg-rose-600 text-white z-50 py-2 px-4 text-center shadow-md flex items-center justify-center gap-2">
        <WifiOff class="w-5 h-5 animate-pulse" />
        <span class="font-bold tracking-wide">離線模式 {{ isSimulatedOffline ? '(DEBUG)' : '' }}</span>
        <span class="text-sm opacity-90">- 資料暫存中，請勿關閉頁面</span>
      </div>
      <div v-else-if="isSyncing" class="fixed top-0 left-0 w-full bg-indigo-600 text-white z-50 py-2 px-4 text-center shadow-md flex items-center justify-center gap-2">
        <RefreshCw class="w-5 h-5 animate-spin" />
        <span class="font-bold tracking-wide">網路恢復，正在上傳 {{ pendingSyncQueue.length }} 筆資料...</span>
      </div>
    </transition>

    <!-- DEBUG 工具列 -->
    <div class="flex justify-end mb-2 mt-6">
        <button 
            @click="toggleDebugOffline"
            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold border shadow-sm transition-all bg-white hover:shadow-md"
            :class="isSimulatedOffline ? 'text-rose-600 border-rose-200 bg-rose-50' : 'text-slate-500 border-slate-200 hover:text-slate-700'"
        >
            <Bug class="w-3.5 h-3.5" />
            {{ isSimulatedOffline ? 'DEBUG: 恢復連線' : 'DEBUG: 模擬斷網' }}
        </button>
    </div>

    <!-- 1. 頂部資訊卡片 -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6 relative overflow-hidden group">
      <div class="absolute right-0 top-0 h-full w-32 bg-gradient-to-l from-slate-50 to-transparent pointer-events-none"></div>
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
          <div class="flex items-center gap-2 text-slate-500 text-sm font-medium mb-1">
            <User class="w-4 h-4 text-indigo-500" /> 
            <span>保管人：</span>
            <span class="text-slate-800 font-bold bg-slate-100 px-2 py-0.5 rounded text-xs">{{ order.responsible_user?.name }}</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">
            {{ order.title }}
          </h1>
          <div class="text-xs text-slate-400 mt-1 flex gap-2">
             <span>工單單號: #{{ order.id.toString().padStart(6, '0') }}</span>
             <span>•</span>
             <span>建立時間: {{ new Date(order.created_at).toLocaleDateString() }}</span>
          </div>
        </div>
        
        <!-- 右側狀態 -->
        <div class="flex items-center gap-6 w-full md:w-auto" v-if="!isScanCompleted">
          <div class="flex-1 md:flex-none text-right">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">盤點進度</div>
            <div class="flex items-baseline justify-end gap-1">
              <span class="text-4xl font-black text-indigo-600">{{ progressPercentage }}%</span>
              <span class="text-sm text-slate-500 font-medium">
                ({{ scanned.length }}/{{ order.items.length }})
              </span>
            </div>
          </div>
          <div class="relative w-16 h-16 hidden md:flex items-center justify-center rounded-full bg-slate-100" 
               :style="`background: conic-gradient(#4f46e5 ${progressPercentage}%, #f1f5f9 0)`">
             <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                <CheckCircle2 class="w-6 h-6 text-indigo-600" />
             </div>
          </div>
        </div>
        <div v-else class="flex items-center gap-2 px-4 py-2 rounded-lg" :class="isFullySynced ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
            <component :is="isFullySynced ? CheckCircle2 : CloudOff" class="w-5 h-5" />
            <span class="font-bold">{{ isFullySynced ? '已完成' : '等待同步' }}</span>
        </div>
      </div>

      <!-- 進度條 -->
      <div v-if="!isScanCompleted" class="w-full bg-slate-100 rounded-full h-2 mt-6 overflow-hidden">
        <div class="h-full rounded-full transition-all duration-700 ease-out bg-indigo-600" :style="{ width: progressPercentage + '%' }"></div>
      </div>
    </div>

    <!-- 2. 內容切換區 -->
    <transition name="fade" mode="out-in">
        
        <!-- 情境 A: 掃描完成 + 且 已全部同步 (完美結案 + 效率展示) -->
        <div v-if="isScanCompleted && isFullySynced && !isReviewMode" class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden animate-bounce-small">
            <div class="bg-emerald-600 p-8 text-white text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-full bg-white opacity-10" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
                        <Trophy class="w-10 h-10 text-emerald-600" />
                    </div>
                    <h2 class="text-3xl font-black mb-1">盤點任務完成！</h2>
                    <p class="opacity-90">所有資料已成功上傳至伺服器</p>
                </div>
            </div>

            <div class="p-8">
                <!-- 效率分析儀表板 -->
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">本次作業效率分析</h3>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <!-- 總耗時 -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                        <div class="text-xs text-slate-400 mb-1">實際耗時</div>
                        <div class="text-2xl font-black text-slate-700">{{ inventoryStats.durationStr }}</div>
                        <div class="text-[10px] text-slate-400">First - Last Scan</div>
                    </div>
                    
                    <!-- 盤點速度 -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                        <div class="text-xs text-slate-400 mb-1">平均速度</div>
                        <div class="text-2xl font-black text-indigo-600">{{ inventoryStats.itemsPerSec }}</div>
                        <div class="text-[10px] text-slate-400">件 / 秒</div>
                    </div>

                    <!-- 節省時間 (重點) -->
                    <div class="p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl border border-indigo-100 text-center col-span-2 md:col-span-2 relative overflow-hidden">
                        <div class="absolute top-0 right-0 bg-indigo-500 text-white text-[10px] px-2 py-0.5 rounded-bl-lg font-bold">效率提升</div>
                        <div class="text-xs text-indigo-400 mb-1 font-bold">預估節省工時</div>
                        <div class="text-3xl font-black text-indigo-600 flex items-center justify-center gap-2">
                            <Timer class="w-6 h-6" />
                            {{ inventoryStats.savedTimeStr }}
                        </div>
                        <div class="text-[10px] text-indigo-400 mt-1">
                            相較傳統人工盤點 ({{ inventoryStats.manualEstStr }})
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 justify-center">
                    <router-link to="/inventory" class="px-8 py-3 bg-white border border-slate-300 text-slate-700 rounded-lg font-bold hover:bg-slate-50 transition-colors">
                        返回列表
                    </router-link>
                    <button @click="isReviewMode = true; activeTab = 'scanned'" class="px-8 py-3 bg-slate-800 text-white rounded-lg font-bold hover:bg-slate-900 shadow-lg transition-colors">
                        檢視明細
                    </button>
                </div>
            </div>
        </div>

        <!-- 情境 B: 掃描完成 + 但 還有資料未同步 (離線等待中) -->
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
                <br>
                <span class="text-rose-500 font-bold">警告：請勿關閉此頁面，否則資料可能會遺失。</span>
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
                <button @click="isReviewMode = true; activeTab = 'scanned'" class="px-6 py-3 bg-white border border-amber-200 text-amber-700 rounded-lg font-bold hover:bg-amber-50 transition-colors">
                    檢視明細
                </button>
            </div>
        </div>

        <!-- 情境 C: 掃描作業畫面 -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-280px)] min-h-[600px]">
            <!-- 左側：掃描輸入 (檢視模式時隱藏) -->
            <div class="lg:col-span-5 flex flex-col gap-6" v-if="!isReviewMode">
                <div 
                    class="bg-white p-8 rounded-2xl shadow-lg border-2 transition-all duration-300 relative overflow-hidden group"
                    :class="statusBorderClass"
                    @click="focusInput"
                >
                    <div class="absolute top-0 left-0 w-full h-1.5 transition-colors duration-300" :class="statusBgClass"></div>
                    <label class="block text-sm font-bold text-slate-600 mb-4 flex items-center gap-2 uppercase tracking-wide justify-between">
                        <div class="flex items-center gap-2">
                            <ScanLine class="w-5 h-5 text-indigo-500" /> 
                            RFID 掃描輸入
                        </div>
                        <div class="flex gap-2">
                            <div v-if="pendingSyncQueue.length > 0" class="flex items-center gap-2 text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full animate-pulse">
                                <CloudOff class="w-3 h-3" /> 待同步: {{ pendingSyncQueue.length }}
                            </div>
                            <div v-if="scanQueue.length > 0" class="flex items-center gap-2 text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded-full animate-pulse">
                                <Layers class="w-3 h-3" /> 處理: {{ scanQueue.length }}
                            </div>
                        </div>
                    </label>
                    <div class="relative">
                        <input ref="scanInput" type="text" :value="displayValue" readonly="true" @keydown="handleKeydown" @blur="handleBlur" class="w-full text-3xl py-6 px-6 border-2 border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 outline-none text-center font-mono font-bold tracking-widest text-slate-800 placeholder:text-slate-200 transition-all cursor-pointer ime-disable" :class="isProcessing ? 'bg-slate-50' : 'bg-white'" placeholder="請使用掃描槍" autocomplete="off">
                        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"><Lock class="w-5 h-5" /></div>
                    </div>
                    <div class="mt-6 min-h-[80px] flex items-center justify-center text-center">
                        <transition name="fade" mode="out-in">
                            <div v-if="inputWarning" key="warning" class="flex flex-col items-center text-amber-500"><AlertTriangle class="w-10 h-10 mb-2" /><span class="text-lg font-bold">偵測到手動輸入</span><span class="text-xs mt-1">請使用掃描槍</span></div>
                            <div v-else-if="msg" :key="msg" class="flex flex-col items-center animate-bounce-small"><component :is="msgIcon" class="w-10 h-10 mb-2" :class="msgIconClass" /><span class="text-lg font-bold" :class="msgTextClass">{{ msg }}</span><span v-if="scannedAssetInfo" class="text-xs text-slate-400 mt-1 font-mono bg-slate-50 px-2 py-1 rounded">{{ scannedAssetInfo }}</span></div>
                            <div v-else class="text-slate-400 flex flex-col items-center"><span class="text-sm" v-if="isOnline">系統待機中...</span><span class="text-sm text-rose-500 font-bold" v-else>離線模式：僅驗證本單資產</span><span class="text-xs mt-1 opacity-60">支援輸入法自動過濾</span></div>
                        </transition>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 flex-1">
                    <div class="bg-emerald-50/80 p-6 rounded-2xl border border-emerald-100 flex flex-col items-center justify-center"><div class="text-emerald-800 text-sm font-bold uppercase tracking-wider mb-1">已盤點</div><div class="text-4xl font-black text-emerald-700">{{ scanned.length }}</div></div>
                    <div class="bg-amber-50/80 p-6 rounded-2xl border border-amber-100 flex flex-col items-center justify-center"><div class="text-amber-800 text-sm font-bold uppercase tracking-wider mb-1">未盤點</div><div class="text-4xl font-black text-amber-700">{{ unscanned.length }}</div></div>
                </div>
            </div>

            <!-- 檢視模式的返回卡片 -->
            <div class="lg:col-span-5 flex flex-col justify-center items-center bg-slate-50 rounded-2xl border border-slate-200 p-8" v-else>
                <component :is="isFullySynced ? Trophy : CloudOff" class="w-16 h-16 mb-4" :class="isFullySynced ? 'text-emerald-500' : 'text-amber-500'" />
                <h3 class="text-xl font-bold text-slate-800">盤點已完成</h3>
                <p class="text-slate-500 mb-6" v-if="!isFullySynced">正在檢視明細 (尚有資料未同步)</p>
                <p class="text-slate-500 mb-6" v-else>正在檢視明細</p>
                <button @click="isReviewMode = false" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm hover:bg-slate-100 shadow-sm">返回結算畫面</button>
            </div>

            <!-- 右側：詳細清單 -->
            <div class="lg:col-span-7 flex flex-col bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-full">
                    <div class="flex border-b border-slate-100 bg-slate-50/50">
                        <button @click="activeTab = 'unscanned'" class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all" :class="activeTab === 'unscanned' ? 'border-amber-500 text-amber-700 bg-amber-50/30' : 'border-transparent text-slate-400'"><ListFilter class="w-4 h-4" /> 待盤點 <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1">{{ unscanned.length }}</span></button>
                        <button @click="activeTab = 'scanned'" class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all" :class="activeTab === 'scanned' ? 'border-emerald-500 text-emerald-700 bg-emerald-50/30' : 'border-transparent text-slate-400'"><History class="w-4 h-4" /> 已盤紀錄 <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1">{{ scanned.length }}</span></button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/30 scrollbar-thin">
                        <transition-group name="list">
                            <div v-for="item in currentList" :key="item.id" class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center relative overflow-hidden">
                                <div v-if="item.status === 'offline_pending'" class="absolute inset-0 bg-amber-50/50 pointer-events-none z-0"></div>
                                <div class="flex items-start gap-3 z-10">
                                    <div class="mt-1">
                                        <div v-if="item.status === '已盤'" class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center"><Check class="w-5 h-5 text-emerald-600" /></div>
                                        <div v-else-if="item.status === 'offline_pending'" class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center animate-pulse"><CloudOff class="w-5 h-5 text-amber-600" /></div>
                                        <div v-else class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center"><Package class="w-5 h-5 text-amber-400" /></div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800">{{ item.fixed_asset.name || '資料同步中...' }}</div>
                                        <div class="text-xs font-mono text-slate-400 flex items-center gap-2 mt-0.5"><span class="bg-slate-100 px-1.5 rounded">{{ item.fixed_asset.asset_tag }}</span><span v-if="item.fixed_asset.rfid_tag" class="text-indigo-400 flex items-center gap-0.5"><Wifi class="w-3 h-3" /> {{ item.fixed_asset.rfid_tag }}</span></div>
                                        <div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><MapPin class="w-3 h-3" /> {{ item.fixed_asset.location || '位置未詳' }}</div>
                                    </div>
                                </div>
                                <div class="z-10 text-right">
                                    <div v-if="item.status === '已盤'"><span class="text-emerald-600 text-xs font-bold bg-emerald-50 px-2 py-1 rounded-full border border-emerald-100">已完成</span></div>
                                    <div v-else-if="item.status === 'offline_pending'"><span class="text-amber-600 text-xs font-bold bg-amber-100 px-2 py-1 rounded-full border border-amber-200 flex items-center gap-1"><RefreshCw class="w-3 h-3 animate-spin" /> 待同步</span></div>
                                </div>
                            </div>
                        </transition-group>
                    </div>
            </div>
        </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import axios from 'axios';
import { 
    User, ScanLine, CheckCircle2, XCircle, MapPin, Inbox, 
    ListFilter, History, Wifi, Check, Package, Trophy, Lock, AlertTriangle, Layers,
    WifiOff, CloudOff, RefreshCw, Bug, Info, Timer
} from 'lucide-vue-next';

const props = defineProps(['id']);
const order = ref(null);
const scanInput = ref(null);
const activeTab = ref('unscanned');
const isReviewMode = ref(false); 

const isSimulatedOffline = ref(false);
const isOnline = ref(navigator.onLine); 
const pendingSyncQueue = ref([]); 
const isSyncing = ref(false); 

const displayValue = ref('');       
const scanBuffer = ref('');         
const lastKeyTime = ref(0);         
const scanQueue = ref([]);          
const isProcessing = ref(false);    
const inputWarning = ref(false);    

const msg = ref('');
const msgType = ref(''); 
const scannedAssetInfo = ref('');

const SCAN_SPEED_THRESHOLD = 60; 

// --- 效率分析參數 (Demo 用) ---
const MANUAL_TIME_PER_ITEM = 30; // 假設人工盤點每件需要 30 秒

const CODE_MAP = {
    'Digit0': '0', 'Digit1': '1', 'Digit2': '2', 'Digit3': '3', 'Digit4': '4',
    'Digit5': '5', 'Digit6': '6', 'Digit7': '7', 'Digit8': '8', 'Digit9': '9',
    'KeyA': 'a', 'KeyB': 'b', 'KeyC': 'c', 'KeyD': 'd', 'KeyE': 'e', 'KeyF': 'f',
    'KeyG': 'g', 'KeyH': 'h', 'KeyI': 'i', 'KeyJ': 'j', 'KeyK': 'k', 'KeyL': 'l',
    'KeyM': 'm', 'KeyN': 'n', 'KeyO': 'o', 'KeyP': 'p', 'KeyQ': 'q', 'KeyR': 'r',
    'KeyS': 's', 'KeyT': 't', 'KeyU': 'u', 'KeyV': 'v', 'KeyW': 'w', 'KeyX': 'x',
    'KeyY': 'y', 'KeyZ': 'z',
    'Minus': '-', 'Equal': '=', 'BracketLeft': '[', 'BracketRight': ']',
    'Backslash': '\\', 'Semicolon': ';', 'Quote': "'", 'Comma': ',', 
    'Period': '.', 'Slash': '/', 'Space': ' '
};
const SHIFT_CODE_MAP = {
    'Digit0': ')', 'Digit1': '!', 'Digit2': '@', 'Digit3': '#', 'Digit4': '$',
    'Digit5': '%', 'Digit6': '^', 'Digit7': '&', 'Digit8': '*', 'Digit9': '(',
    'Minus': '_', 'Equal': '+', 'BracketLeft': '{', 'BracketRight': '}',
    'Backslash': '|', 'Semicolon': ':', 'Quote': '"', 'Comma': '<', 
    'Period': '>', 'Slash': '?'
};

const unscanned = computed(() => order.value?.items.filter(i => i.status === '未盤') || []);
const scanned = computed(() => order.value?.items.filter(i => i.status === '已盤' || i.status === 'offline_pending')
    .sort((a,b) => {
        const dateA = a.scanned_at ? new Date(a.scanned_at) : new Date();
        const dateB = b.scanned_at ? new Date(b.scanned_at) : new Date();
        return dateB - dateA;
    }) || []);
const currentList = computed(() => activeTab.value === 'unscanned' ? unscanned.value : scanned.value);
const progressPercentage = computed(() => {
    if (!order.value?.items?.length) return 0;
    return Math.round((scanned.value.length / order.value.items.length) * 100);
});

const isScanCompleted = computed(() => {
    if (!order.value) return false;
    if (order.value.items.length === 0) return false;
    return order.value.items.every(item => item.status === '已盤' || item.status === 'offline_pending');
});

const isFullySynced = computed(() => {
    return pendingSyncQueue.value.length === 0;
});

// ★★★ 效率計算統計 ★★★
const inventoryStats = computed(() => {
    if (!isScanCompleted.value) return { durationStr: '--', itemsPerSec: '--', savedTimeStr: '--', manualEstStr: '--' };
    
    // 1. 找出所有掃描時間點
    const timestamps = scanned.value
        .map(i => i.scanned_at ? new Date(i.scanned_at).getTime() : 0)
        .filter(t => t > 0)
        .sort((a, b) => a - b);

    if (timestamps.length === 0) return { durationStr: '0秒', itemsPerSec: '∞', savedTimeStr: '0秒', manualEstStr: '0秒' };

    // 2. 計算實際耗時 (第一筆到最後一筆)
    const startTime = timestamps[0];
    const endTime = timestamps[timestamps.length - 1];
    let durationSec = (endTime - startTime) / 1000;
    
    // 如果只有一筆或時間太短 (例如模擬生成)，給一個最小時間避免除以零
    if (durationSec < 1) durationSec = 1;

    // 3. 效率計算
    const totalItems = scanned.value.length;
    const itemsPerSec = (totalItems / durationSec).toFixed(1);
    
    // 4. 人工預估與節省
    const manualEstSec = totalItems * MANUAL_TIME_PER_ITEM;
    const savedSec = manualEstSec - durationSec;

    // 格式化函數
    const formatTime = (sec) => {
        if (sec < 60) return `${Math.round(sec)}秒`;
        const m = Math.floor(sec / 60);
        const s = Math.round(sec % 60);
        return `${m}分${s}秒`;
    };

    return {
        durationStr: formatTime(durationSec),
        itemsPerSec: itemsPerSec,
        manualEstStr: formatTime(manualEstSec),
        savedTimeStr: formatTime(savedSec > 0 ? savedSec : 0)
    };
});

// UI Logic
const statusBorderClass = computed(() => {
    if (inputWarning.value) return 'border-amber-500 ring-4 ring-amber-100';
    if (msgType.value === 'success') return 'border-emerald-500 ring-4 ring-emerald-100';
    if (msgType.value === 'error') return 'border-rose-500 ring-4 ring-rose-100';
    if (msgType.value === 'info') return 'border-blue-400 ring-4 ring-blue-100';
    if (!isOnline.value) return 'border-rose-200 hover:border-rose-300 ring-4 ring-rose-50';
    return 'border-slate-200 hover:border-indigo-300';
});

const statusBgClass = computed(() => {
    if (inputWarning.value) return 'bg-amber-500';
    if (msgType.value === 'success') return 'bg-emerald-500';
    if (msgType.value === 'error') return 'bg-rose-500';
    if (msgType.value === 'info') return 'bg-blue-400';
    if (!isOnline.value) return 'bg-rose-500'; 
    return 'bg-indigo-500';
});

const msgIcon = computed(() => {
    if (msgType.value === 'offline') return CloudOff;
    if (msgType.value === 'info') return Info;
    return msgType.value === 'success' ? CheckCircle2 : XCircle;
});
const msgIconClass = computed(() => {
    if (msgType.value === 'offline') return 'text-amber-500';
    if (msgType.value === 'info') return 'text-blue-500';
    return msgType.value === 'success' ? 'text-emerald-500' : 'text-rose-500';
});
const msgTextClass = computed(() => {
    if (msgType.value === 'offline') return 'text-amber-700';
    if (msgType.value === 'info') return 'text-blue-700';
    return msgType.value === 'success' ? 'text-emerald-700' : 'text-rose-700';
});

const updateOnlineStatus = () => {
    if (isSimulatedOffline.value) {
        isOnline.value = false;
    } else {
        isOnline.value = navigator.onLine;
        if (isOnline.value && pendingSyncQueue.value.length > 0) {
            syncOfflineData();
        }
    }
};

const toggleDebugOffline = () => {
    isSimulatedOffline.value = !isSimulatedOffline.value;
    updateOnlineStatus();
};

const syncOfflineData = async () => {
    if (isSyncing.value || pendingSyncQueue.value.length === 0) return;
    isSyncing.value = true;

    const processNext = async () => {
        if (pendingSyncQueue.value.length === 0) {
            isSyncing.value = false;
            msg.value = '所有離線資料同步完成！';
            msgType.value = 'success';
            setTimeout(() => { if(msgType.value === 'success') msg.value = ''; }, 3000);
            return;
        }

        const rfid = pendingSyncQueue.value[0];

        try {
            const res = await axios.post(`/api/inventory/work-orders/${props.id}/scan`, { 
                rfid_tag: rfid 
            });

            const idx = order.value.items.findIndex(i => i.fixed_asset?.rfid_tag === rfid);
            if (idx !== -1) {
                order.value.items[idx] = res.data;
            }

            pendingSyncQueue.value.shift();
            setTimeout(processNext, 100);

        } catch (e) {
            if (e.response && (e.response.status === 404 || e.response.status === 422)) {
                 pendingSyncQueue.value.shift();
                 const idx = order.value.items.findIndex(i => i.fixed_asset?.rfid_tag === rfid);
                 if (idx !== -1) order.value.items[idx].status = '未盤';
                 setTimeout(processNext, 100);
            } else {
                isSyncing.value = false;
                msg.value = '同步中斷：網路不穩定';
                msgType.value = 'error';
            }
        }
    };

    processNext();
};

const handleKeydown = (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (isScanCompleted.value && !isReviewMode.value) return;

    const now = Date.now();
    const code = e.code;
    const timeDiff = now - lastKeyTime.value;
    lastKeyTime.value = now;

    if (code === 'Enter' || code === 'NumpadEnter') {
        if (scanBuffer.value.length > 0) {
            if (timeDiff > SCAN_SPEED_THRESHOLD) {
                resetBufferWithWarning();
                return;
            }
            scanQueue.value.push(scanBuffer.value);
            displayValue.value = scanBuffer.value;
            setTimeout(() => { if(displayValue.value === scanBuffer.value) displayValue.value = ''; }, 500);
            scanBuffer.value = '';
            inputWarning.value = false;
        }
        return;
    }

    let char = '';
    if (e.shiftKey) {
        if (SHIFT_CODE_MAP[code]) char = SHIFT_CODE_MAP[code];
        else if (CODE_MAP[code] && /^[a-z]$/.test(CODE_MAP[code])) char = CODE_MAP[code].toUpperCase();
    } else {
        if (CODE_MAP[code]) char = CODE_MAP[code];
    }
    if (code.startsWith('Numpad') && code.length === 7) char = code[6];

    if (char) {
        if (scanBuffer.value.length > 0 && timeDiff > SCAN_SPEED_THRESHOLD) {
            resetBufferWithWarning();
            return;
        }
        inputWarning.value = false;
        scanBuffer.value += char.toUpperCase();
        displayValue.value = scanBuffer.value;
    }
};

const resetBufferWithWarning = () => {
    scanBuffer.value = '';
    displayValue.value = '';
    inputWarning.value = true;
    setTimeout(() => inputWarning.value = false, 1500);
};

watch(() => scanQueue.value.length, (newLength) => {
    if (newLength > 0 && !isProcessing.value) {
        processQueue();
    }
});

const processQueue = async () => {
    if (scanQueue.value.length === 0) {
        isProcessing.value = false;
        return;
    }
    isProcessing.value = true;
    const currentRfid = scanQueue.value.shift(); 
    displayValue.value = '';

    if (!isOnline.value) {
        const targetItemIndex = order.value.items.findIndex(item => item.fixed_asset?.rfid_tag === currentRfid);

        if (targetItemIndex !== -1) {
            const targetItem = order.value.items[targetItemIndex];
            if (targetItem.status === '已盤' || targetItem.status === 'offline_pending') {
                 msg.value = `提示: 重複掃描 (${targetItem.fixed_asset.name})`;
                 scannedAssetInfo.value = currentRfid;
                 msgType.value = 'info'; 
            } else {
                targetItem.status = 'offline_pending';
                targetItem.scanned_at = new Date().toISOString();
                pendingSyncQueue.value.push(currentRfid);
                msg.value = `離線暫存成功: ${targetItem.fixed_asset.name}`;
                scannedAssetInfo.value = currentRfid;
                msgType.value = 'offline';
            }
        } else {
            msg.value = '提示：不屬於當前工單';
            scannedAssetInfo.value = currentRfid;
            msgType.value = 'info'; 
        }
        
        setTimeout(() => { processQueue(); }, 200);
        return;
    }

    try {
        const res = await axios.post(`/api/inventory/work-orders/${props.id}/scan`, { 
            rfid_tag: currentRfid 
        });
        const idx = order.value.items.findIndex(i => i.id === res.data.id);
        if(idx !== -1) order.value.items[idx] = res.data;
        msg.value = `盤點成功: ${res.data.fixed_asset.name}`;
        scannedAssetInfo.value = currentRfid;
        msgType.value = 'success';
    } catch (e) {
        const status = e.response?.status;
        const errMsg = e.response?.data?.message || '無效的標籤';
        scannedAssetInfo.value = currentRfid;

        if (status === 404 || status === 422) {
            msg.value = `提示：${status === 422 ? '非本單資產' : '未知標籤'}`;
            msgType.value = 'info';
        } else {
            msg.value = errMsg; 
            msgType.value = 'error';
        }
    }
    setTimeout(() => { processQueue(); }, 300); 
};

const focusInput = () => { scanInput.value?.focus(); };
const handleBlur = () => { setTimeout(() => { if (document.activeElement === document.body) focusInput(); }, 100); };

const fetch = async () => {
    try {
        const res = await axios.get('/api/inventory/work-orders/' + props.id);
        order.value = res.data;
        nextTick(focusInput);
    } catch(e) { alert('無法載入工單'); }
};

onMounted(() => {
    fetch();
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
});

onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});
</script>

<style scoped>
.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateX(20px); }
@keyframes bounce-small { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
.animate-bounce-small { animation: bounce-small 0.5s ease-in-out; }
.scrollbar-thin::-webkit-scrollbar { width: 6px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
.ime-disable { ime-mode: disabled; }
.slide-down-enter-active, .slide-down-leave-active { transition: transform 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); }
</style>