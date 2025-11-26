<template>
    <div class="flex flex-col gap-6">
        <div 
            class="bg-white p-8 rounded-2xl shadow-lg border-2 transition-all duration-300 relative overflow-hidden group"
            :class="statusBorderClass"
            @click="focusInput"
        >
            <!-- 頂部顏色條 -->
            <div class="absolute top-0 left-0 w-full h-1.5 transition-colors duration-300" :class="statusBgClass"></div>
            
            <label class="block text-sm font-bold text-slate-600 mb-4 flex items-center gap-2 uppercase tracking-wide justify-between">
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
                    class="w-full text-3xl py-6 px-6 border-2 border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 outline-none text-center font-mono font-bold tracking-widest text-slate-800 placeholder:text-slate-200 transition-all cursor-pointer ime-disable" 
                    :class="scanState.isProcessing ? 'bg-slate-50' : 'bg-white'" 
                    placeholder="請使用掃描槍" 
                    autocomplete="off"
                >
                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-300"><Lock class="w-5 h-5" /></div>
            </div>

            <!-- 訊息區域 -->
            <div class="mt-6 min-h-[80px] flex items-center justify-center text-center">
                <transition name="fade" mode="out-in">
                    <!-- 警告 -->
                    <div v-if="scanState.inputWarning" key="warning" class="flex flex-col items-center text-amber-500">
                        <AlertTriangle class="w-10 h-10 mb-2" />
                        <span class="text-lg font-bold">偵測到手動輸入</span>
                        <span class="text-xs mt-1">請使用掃描槍</span>
                    </div>
                    
                    <!-- 結果訊息 -->
                    <div v-else-if="scanState.msg" :key="scanState.msg" class="flex flex-col items-center animate-bounce-small">
                        <component :is="msgIcon" class="w-10 h-10 mb-2" :class="msgIconClass" />
                        <span class="text-lg font-bold" :class="msgTextClass">{{ scanState.msg }}</span>
                        <!-- 
                             如果是 neutral (雜訊)，小字顯示灰色 
                             如果是 success，小字顯示淡綠色或其他對比色 
                        -->
                        <span v-if="scanState.scannedAssetInfo" 
                              class="text-xs mt-1 font-mono px-2 py-1 rounded transition-colors"
                              :class="scanState.msgType === 'neutral' ? 'text-slate-400 bg-slate-50' : 'text-slate-500 bg-slate-50'">
                            {{ scanState.scannedAssetInfo }}
                        </span>
                    </div>
                    
                    <!-- 待機 -->
                    <div v-else class="text-slate-400 flex flex-col items-center">
                        <span class="text-sm" v-if="isOnline">系統待機中...</span>
                        <span class="text-sm text-rose-500 font-bold" v-else>離線模式：僅驗證本單資產</span>
                        <span class="text-xs mt-1 opacity-60">支援連發掃描</span>
                    </div>
                </transition>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 flex-1">
            <div class="bg-emerald-50/80 p-6 rounded-2xl border border-emerald-100 flex flex-col items-center justify-center">
                <div class="text-emerald-800 text-sm font-bold uppercase tracking-wider mb-1">已盤點</div>
                <div class="text-4xl font-black text-emerald-700">{{ counts.scanned }}</div>
            </div>
            <div class="bg-amber-50/80 p-6 rounded-2xl border border-amber-100 flex flex-col items-center justify-center">
                <div class="text-amber-800 text-sm font-bold uppercase tracking-wider mb-1">未盤點</div>
                <div class="text-4xl font-black text-amber-700">{{ counts.unscanned }}</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { ScanLine, CloudOff, Layers, Lock, AlertTriangle, CheckCircle2, XCircle, Info, MinusCircle } from 'lucide-vue-next';

const props = defineProps({
    scanState: Object, 
    counts: Object,    
    pendingCount: Number,
    queueCount: Number,
    isOnline: Boolean
});

const emit = defineEmits(['keydown', 'focus']);
const inputRef = ref(null);

const onKeydown = (e) => emit('keydown', e);
const focusInput = () => inputRef.value?.focus();
const handleBlur = () => { setTimeout(() => { if (document.activeElement === document.body) focusInput(); }, 100); };

defineExpose({ focusInput });
onMounted(() => nextTick(focusInput));

// UI Computed Logic
const statusBorderClass = computed(() => {
    if (props.scanState.inputWarning) return 'border-amber-500 ring-4 ring-amber-100';
    if (props.scanState.msgType === 'success') return 'border-emerald-500 ring-4 ring-emerald-100';
    if (props.scanState.msgType === 'error') return 'border-rose-500 ring-4 ring-rose-100';
    if (props.scanState.msgType === 'info') return 'border-blue-400 ring-4 ring-blue-100';
    
    // ★ 新增：neutral (雜訊) 使用淡灰色，不刺眼
    if (props.scanState.msgType === 'neutral') return 'border-slate-300';
    
    if (!props.isOnline) return 'border-rose-200 hover:border-rose-300 ring-4 ring-rose-50';
    return 'border-slate-200 hover:border-indigo-300';
});

const statusBgClass = computed(() => {
    if (props.scanState.inputWarning) return 'bg-amber-500';
    if (props.scanState.msgType === 'success') return 'bg-emerald-500';
    if (props.scanState.msgType === 'error') return 'bg-rose-500';
    if (props.scanState.msgType === 'info') return 'bg-blue-400';
    
    // ★ 新增：neutral (雜訊) 使用淡灰色
    if (props.scanState.msgType === 'neutral') return 'bg-slate-300';
    
    if (!props.isOnline) return 'bg-rose-500'; 
    return 'bg-indigo-500';
});

const msgIcon = computed(() => {
    if (props.scanState.msgType === 'offline') return CloudOff;
    if (props.scanState.msgType === 'info') return Info;
    // ★ 新增：neutral 圖標
    if (props.scanState.msgType === 'neutral') return MinusCircle;
    
    return props.scanState.msgType === 'success' ? CheckCircle2 : XCircle;
});

const msgIconClass = computed(() => {
    if (props.scanState.msgType === 'offline') return 'text-amber-500';
    if (props.scanState.msgType === 'info') return 'text-blue-500';
    // ★ 新增：neutral 顏色
    if (props.scanState.msgType === 'neutral') return 'text-slate-400';
    
    return props.scanState.msgType === 'success' ? 'text-emerald-500' : 'text-rose-500';
});

const msgTextClass = computed(() => {
    if (props.scanState.msgType === 'offline') return 'text-amber-700';
    if (props.scanState.msgType === 'info') return 'text-blue-700';
    // ★ 新增：neutral 文字顏色
    if (props.scanState.msgType === 'neutral') return 'text-slate-500';
    
    return props.scanState.msgType === 'success' ? 'text-emerald-700' : 'text-rose-700';
});
</script>

<style scoped>
.ime-disable { ime-mode: disabled; }
.animate-bounce-small { animation: bounce-small 0.5s ease-in-out; }
@keyframes bounce-small { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
</style>