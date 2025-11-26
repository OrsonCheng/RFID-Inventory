<template>
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
        <div class="flex items-center gap-6 w-full md:w-auto" v-if="!isCompleted">
          <div class="flex-1 md:flex-none text-right">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">盤點進度</div>
            <div class="flex items-baseline justify-end gap-1">
              <span class="text-4xl font-black text-indigo-600">{{ progress }}%</span>
              <span class="text-sm text-slate-500 font-medium">
                ({{ scannedCount }}/{{ totalCount }})
              </span>
            </div>
          </div>
          <!-- 圓形進度條 -->
          <div class="relative w-16 h-16 hidden md:flex items-center justify-center rounded-full bg-slate-100" 
               :style="`background: conic-gradient(#4f46e5 ${progress}%, #f1f5f9 0)`">
             <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                <CheckCircle2 class="w-6 h-6 text-indigo-600" />
             </div>
          </div>
        </div>
        
        <!-- 完成狀態 -->
        <div v-else class="flex items-center gap-2 px-4 py-2 rounded-lg" :class="isSynced ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
            <component :is="isSynced ? CheckCircle2 : CloudOff" class="w-5 h-5" />
            <span class="font-bold">{{ isSynced ? '已完成' : '等待同步' }}</span>
        </div>
      </div>

      <!-- 線性進度條 -->
      <div v-if="!isCompleted" class="w-full bg-slate-100 rounded-full h-2 mt-6 overflow-hidden">
        <div class="h-full rounded-full transition-all duration-700 ease-out bg-indigo-600" :style="{ width: progress + '%' }"></div>
      </div>
    </div>
</template>

<script setup>
import { User, CheckCircle2, CloudOff } from 'lucide-vue-next';

defineProps({
    order: Object,
    progress: Number,
    scannedCount: Number,
    totalCount: Number,
    isCompleted: Boolean,
    isSynced: Boolean
});
</script>