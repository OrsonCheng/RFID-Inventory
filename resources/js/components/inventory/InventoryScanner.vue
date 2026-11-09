<template>
    <div class="flex flex-col gap-5">
        <!-- 1. 主掃描輸入卡片 (相容藍牙/有線單筆連掃 & 顯示批次核對結果) -->
        <div 
            class="bg-white p-6 rounded-2xl shadow-lg border-2 transition-all duration-300 relative overflow-hidden group"
            :class="statusBorderClass"
            @click="focusInput"
        >
            <!-- 頂部顏色條 -->
            <div class="absolute top-0 left-0 w-full h-1.5 transition-colors duration-300" :class="statusBgClass"></div>
            
            <label class="block text-sm font-bold text-slate-600 mb-3 flex items-center gap-2 uppercase tracking-wide justify-between">
                <div class="flex items-center gap-2">
                    <ScanLine class="w-5 h-5 text-indigo-500" /> 
                    RFID 掃描輸入
                </div>
                <div class="flex gap-2">
                    <div v-if="pendingCount > 0" class="flex items-center gap-2 text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full animate-pulse">
                        <CloudOff class="w-3 h-3" /> 待同步: {{ pendingCount }}
                    </div>
                    <div v-if="queueCount > 0" class="flex items-center gap-2 text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded-full animate-pulse">
                        <Layers class="w-3 h-3" /> 批次: {{ queueCount }}
                    </div>
                </div>
            </label>
            
            <div class="relative">
                <input 
                    ref="inputRef" 
                    type="text" 
                    :value="scanState.displayValue" 
                    readonly="true" 
                    @keydown="onKeydown" 
                    @blur="handleBlur" 
                    class="w-full text-2xl py-4 px-5 border-2 border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 outline-none text-center font-mono font-bold tracking-widest text-slate-800 placeholder:text-slate-200 transition-all cursor-pointer ime-disable" 
                    :class="scanState.isProcessing ? 'bg-slate-50' : 'bg-white'" 
                    placeholder="請使用掃描槍" 
                    autocomplete="off"
                >
                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"><Lock class="w-5 h-5" /></div>
            </div>

            <!-- 訊息區域 -->
            <div class="mt-4 min-h-[72px] flex items-center justify-center text-center">
                <transition name="fade" mode="out-in">
                    <!-- 警告 -->
                    <div v-if="scanState.inputWarning" key="warning" class="flex flex-col items-center text-amber-500">
                        <AlertTriangle class="w-9 h-9 mb-1.5" />
                        <span class="text-base font-bold">偵測到手動輸入</span>
                        <span class="text-xs mt-0.5">請使用掃描槍</span>
                    </div>
                    
                    <!-- 結果訊息 -->
                    <div v-else-if="scanState.msg" :key="scanState.msg" class="flex flex-col items-center animate-bounce-small">
                        <component :is="msgIcon" class="w-9 h-9 mb-1.5" :class="msgIconClass" />
                        <span class="text-base font-bold" :class="msgTextClass">{{ scanState.msg }}</span>
                        <span v-if="scanState.scannedAssetInfo" 
                              class="text-xs mt-1 font-mono px-2 py-0.5 rounded transition-colors"
                              :class="scanState.msgType === 'neutral' ? 'text-slate-400 bg-slate-50' : 'text-slate-500 bg-slate-50'">
                            {{ scanState.scannedAssetInfo }}
                        </span>
                    </div>
                    
                    <!-- 待機 -->
                    <div v-else class="text-slate-400 flex flex-col items-center">
                        <span class="text-sm" v-if="isOnline">系統待機中...</span>
                        <span class="text-sm text-rose-500 font-bold" v-else>離線模式：僅驗證本單資產</span>
                        <span class="text-xs mt-1 opacity-60">支援下方 USB 離線高速匯入或即時掃描</span>
                    </div>
                </transition>
            </div>
        </div>

        <!-- 2. ★ 新增：USB 離線暫存高速匯入面板 (UR-94E4 WebHID) -->
        <div class="bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-900 text-white p-5 rounded-2xl shadow-lg border border-indigo-500/30 relative overflow-hidden">
            <!-- 標題與連線狀態列 -->
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-indigo-500/20 border border-indigo-400/30 text-indigo-300">
                        <Usb class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="text-sm font-bold tracking-wide flex items-center gap-2">
                            <span>USB 離線暫存高速上傳</span>
                            <span
                                class="text-[11px] px-2 py-0.5 rounded-full font-semibold border"
                                :class="isConnected
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                                    : 'bg-slate-800 text-slate-400 border-slate-700'"
                            >
                                {{ isConnected ? '🟢 已連線' : '⚪ 未連接' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            現場離線掃描完畢後，插上 USB 線即可 0.1 秒批次匯入全數標籤
                        </p>
                    </div>
                </div>

                <!-- 槍內暫存筆數徽章 -->
                <div
                    v-if="isConnected && storedTagCount !== null"
                    class="px-3 py-1 rounded-xl bg-indigo-500/20 border border-indigo-400/40 text-right shrink-0"
                >
                    <div class="text-[10px] text-indigo-300 uppercase">槍內暫存</div>
                    <div class="text-base font-black font-mono text-white leading-tight">
                        {{ storedTagCount }} <span class="text-xs font-normal text-indigo-200">筆</span>
                    </div>
                </div>
            </div>

            <!-- 操作按鈕群 -->
            <div class="grid grid-cols-12 gap-2 mt-3">
                <!-- 主按鈕：一鍵連接 / 一鍵匯入盤點單 -->
                <button
                    v-if="!isConnected"
                    type="button"
                    @click.stop="connectScanner"
                    class="col-span-9 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-indigo-950/50 transition flex items-center justify-center gap-2"
                >
                    <Usb class="w-4 h-4" />
                    <span>🔌 連接 USB 掃描槍 (UR-94E4)</span>
                </button>

                <button
                    v-else
                    type="button"
                    :disabled="isExporting || scanState.isProcessing"
                    @click.stop="importOfflineTags"
                    class="col-span-7 py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-emerald-950/50 transition flex items-center justify-center gap-1.5"
                >
                    <RefreshCw v-if="isExporting" class="w-4 h-4 animate-spin" />
                    <Zap v-else class="w-4 h-4" />
                    <span>{{ isExporting ? '高速讀取中...' : '⚡ 一鍵匯入盤點單' }}</span>
                </button>

                <!-- 附屬按鈕：重讀筆數 & 清空暫存 -->
                <template v-if="isConnected">
                    <button
                        type="button"
                        :disabled="isExporting"
                        @click.stop="readTagCount"
                        title="重新讀取掃描槍內暫存筆數"
                        class="col-span-2 py-2.5 px-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-semibold transition flex items-center justify-center gap-1"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                        <span>查筆數</span>
                    </button>
                    <button
                        type="button"
                        :disabled="isExporting || isClearing"
                        @click.stop="clearScannerMemory(false)"
                        title="清空掃描槍內部暫存標籤 (小螢幕歸零)"
                        class="col-span-3 py-2.5 px-2 rounded-xl bg-rose-950/70 hover:bg-rose-900/80 border border-rose-500/40 text-rose-200 text-xs font-semibold transition flex items-center justify-center gap-1"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>清空槍暫存</span>
                    </button>
                </template>

                <!-- 若尚未連線，提供瀏覽器設定說明按鈕 -->
                <button
                    v-else
                    type="button"
                    @click.stop="showFlagGuideModal = true"
                    title="首次使用 USB 連線設定說明"
                    class="col-span-3 py-2.5 px-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-semibold transition flex items-center justify-center gap-1"
                >
                    <HelpCircle class="w-3.5 h-3.5 text-amber-400" />
                    <span>連線設定</span>
                </button>
            </div>

            <!-- 底部勾選與狀態提示列 -->
            <div class="mt-3 pt-2.5 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-xs">
                <label class="flex items-center gap-1.5 text-slate-300 cursor-pointer select-none" @click.stop>
                    <input
                        type="checkbox"
                        v-model="autoClearAfterImport"
                        class="rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-0 w-3.5 h-3.5"
                    />
                    <span>匯入後自動清空掃描槍暫存 (小螢幕歸零)</span>
                </label>

                <span
                    v-if="statusText"
                    class="text-[11px] font-mono truncate max-w-full"
                    :class="{
                        'text-emerald-300': statusType === 'success',
                        'text-amber-300': statusType === 'warning',
                        'text-rose-300': statusType === 'error',
                        'text-indigo-300': statusType === 'info',
                        'text-slate-400': statusType === 'idle'
                    }"
                >
                    {{ statusText }}
                </span>
            </div>
        </div>

        <!-- 3. 已盤點 / 未盤點 統計卡片 -->
        <div class="grid grid-cols-2 gap-4 flex-1">
            <div class="bg-emerald-50/80 p-5 rounded-2xl border border-emerald-100 flex flex-col items-center justify-center">
                <div class="text-emerald-800 text-sm font-bold uppercase tracking-wider mb-1">已盤點</div>
                <div class="text-4xl font-black text-emerald-700">{{ counts.scanned }}</div>
            </div>
            <div class="bg-amber-50/80 p-5 rounded-2xl border border-amber-100 flex flex-col items-center justify-center">
                <div class="text-amber-800 text-sm font-bold uppercase tracking-wider mb-1">未盤點</div>
                <div class="text-4xl font-black text-amber-700">{{ counts.unscanned }}</div>
            </div>
        </div>

        <!-- 4. ★ Chrome / Edge 安全來源白名單設定引導 Modal (方案 A) -->
        <transition name="fade">
            <div v-if="showFlagGuideModal" class="fixed inset-0 z-[120] flex items-center justify-center px-4" @click.stop>
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showFlagGuideModal = false"></div>
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 overflow-hidden border border-slate-200">
                    <div class="bg-indigo-600 px-6 py-4 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <Usb class="w-5 h-5" />
                            <h3 class="text-base font-bold">首次啟用 USB 掃描槍高速連線設定 (10 秒完成)</h3>
                        </div>
                        <button @click="showFlagGuideModal = false" class="text-indigo-200 hover:text-white text-lg font-bold">✕</button>
                    </div>

                    <div class="p-6 space-y-4 text-sm text-slate-700">
                        <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs leading-relaxed">
                            💡 由於正式環境網址為 <b>{{ currentOrigin }}</b> (非 HTTPS)，Chrome / Edge 預設需將此網址加入「安全來源白名單」才能啟用 USB WebHID 直接讀取掃描槍（只需設定一次，永久有效）。
                        </div>

                        <div class="space-y-3">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="font-bold text-slate-800 mb-1">步驟 1：複製下方設定網址，貼到瀏覽器新分頁網址列並按 Enter</div>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <code class="flex-1 bg-slate-900 text-emerald-300 px-3 py-1.5 rounded-lg text-xs font-mono select-all overflow-x-auto">
                                        chrome://flags/#unsafely-treat-insecure-origin-as-secure
                                    </code>
                                    <button
                                        type="button"
                                        @click="copyText('chrome://flags/#unsafely-treat-insecure-origin-as-secure', 'flag')"
                                        class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shrink-0 flex items-center gap-1"
                                    >
                                        <Copy class="w-3.5 h-3.5" />
                                        <span>{{ copiedKey === 'flag' ? '已複製！' : '複製網址' }}</span>
                                    </button>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">
                                    (若使用 Edge 瀏覽器，貼上後會自動轉為 <code>edge://flags/#unsafely-treat-insecure-origin-as-secure</code>)
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="font-bold text-slate-800 mb-1">步驟 2：在黃色標記的輸入框填入系統網域，並設為 Enabled</div>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <code class="flex-1 bg-slate-900 text-amber-300 px-3 py-1.5 rounded-lg text-xs font-mono select-all overflow-x-auto">
                                        {{ currentOrigin }}
                                    </code>
                                    <button
                                        type="button"
                                        @click="copyText(currentOrigin, 'origin')"
                                        class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shrink-0 flex items-center gap-1"
                                    >
                                        <Copy class="w-3.5 h-3.5" />
                                        <span>{{ copiedKey === 'origin' ? '已複製！' : '複製網域' }}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="font-bold text-slate-800">
                                    步驟 3：將右側下拉選單改為 <span class="text-indigo-600 font-mono">Enabled</span>，點擊右下角藍色 <span class="text-indigo-600 font-mono">Relaunch</span> 重啟瀏覽器即可！
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="button"
                                @click="showFlagGuideModal = false"
                                class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs"
                            >
                                我知道了
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import {
    ScanLine, CloudOff, Layers, Lock, AlertTriangle,
    CheckCircle2, XCircle, Info, MinusCircle,
    Usb, Zap, RefreshCw, Trash2, HelpCircle, Copy
} from 'lucide-vue-next';
import { useUsbHidScanner } from '../../composables/useUsbHidScanner';

const props = defineProps({
    scanState: Object, 
    counts: Object,    
    pendingCount: Number,
    queueCount: Number,
    isOnline: Boolean
});

const emit = defineEmits(['keydown', 'focus', 'batch-import']);
const inputRef = ref(null);
const copiedKey = ref('');

const currentOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://ses.simplo.com.cn';

// 初始化 USB WebHID 離線批次匯入 Composable
const {
    isConnected,
    storedTagCount,
    isExporting,
    isClearing,
    autoClearAfterImport,
    statusText,
    statusType,
    showFlagGuideModal,
    connectScanner,
    readTagCount,
    importOfflineTags,
    clearScannerMemory,
} = useUsbHidScanner(async (importedTags) => {
    emit('batch-import', importedTags);
});

const copyText = async (text, key) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = '';
        }, 2000);
    } catch (_) {
        // Fallback copy
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = '';
        }, 2000);
    }
};

const onKeydown = (e) => emit('keydown', e);
const focusInput = () => inputRef.value?.focus();
const handleBlur = () => {
    setTimeout(() => {
        if (document.activeElement === document.body && !showFlagGuideModal.value) {
            focusInput();
        }
    }, 100);
};

defineExpose({ focusInput });
onMounted(() => nextTick(focusInput));

// UI Computed Logic
const statusBorderClass = computed(() => {
    if (props.scanState.inputWarning) return 'border-amber-500 ring-4 ring-amber-100';
    if (props.scanState.msgType === 'success') return 'border-emerald-500 ring-4 ring-emerald-100';
    if (props.scanState.msgType === 'error') return 'border-rose-500 ring-4 ring-rose-100';
    if (props.scanState.msgType === 'info') return 'border-blue-400 ring-4 ring-blue-100';
    if (props.scanState.msgType === 'neutral') return 'border-slate-300';
    if (!props.isOnline) return 'border-rose-200 hover:border-rose-300 ring-4 ring-rose-50';
    return 'border-slate-200 hover:border-indigo-300';
});

const statusBgClass = computed(() => {
    if (props.scanState.inputWarning) return 'bg-amber-500';
    if (props.scanState.msgType === 'success') return 'bg-emerald-500';
    if (props.scanState.msgType === 'error') return 'bg-rose-500';
    if (props.scanState.msgType === 'info') return 'bg-blue-400';
    if (props.scanState.msgType === 'neutral') return 'bg-slate-300';
    if (!props.isOnline) return 'bg-rose-500'; 
    return 'bg-indigo-500';
});

const msgIcon = computed(() => {
    if (props.scanState.msgType === 'offline') return CloudOff;
    if (props.scanState.msgType === 'info') return Info;
    if (props.scanState.msgType === 'neutral') return MinusCircle;
    return props.scanState.msgType === 'success' ? CheckCircle2 : XCircle;
});

const msgIconClass = computed(() => {
    if (props.scanState.msgType === 'offline') return 'text-amber-500';
    if (props.scanState.msgType === 'info') return 'text-blue-500';
    if (props.scanState.msgType === 'neutral') return 'text-slate-400';
    return props.scanState.msgType === 'success' ? 'text-emerald-500' : 'text-rose-500';
});

const msgTextClass = computed(() => {
    if (props.scanState.msgType === 'offline') return 'text-amber-700';
    if (props.scanState.msgType === 'info') return 'text-blue-700';
    if (props.scanState.msgType === 'neutral') return 'text-slate-500';
    return props.scanState.msgType === 'success' ? 'text-emerald-700' : 'text-rose-700';
});
</script>

<style scoped>
.ime-disable { ime-mode: disabled; }
.animate-bounce-small { animation: bounce-small 0.5s ease-in-out; }
@keyframes bounce-small { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
</style>
