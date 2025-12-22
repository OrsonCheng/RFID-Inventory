<template>
  <div
    class="flex flex-col bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-full"
  >
    <!-- Tabs -->
    <div class="flex border-b border-slate-100 bg-slate-50/50 flex-shrink-0">
      <!-- 待盤點 Tab -->
      <button
        @click="switchTab('unscanned')"
        class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all"
        :class="
          currentTab === 'unscanned'
            ? 'border-amber-500 text-amber-700 bg-amber-50/30'
            : 'border-transparent text-slate-400'
        "
      >
        <ListFilter class="w-4 h-4" /> 待盤點
        <span
          class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1"
          >{{ unscanned.length }}</span
        >
      </button>

      <!-- 已盤紀錄 Tab -->
      <button
        @click="switchTab('scanned')"
        class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all"
        :class="
          currentTab === 'scanned'
            ? 'border-emerald-500 text-emerald-700 bg-emerald-50/30'
            : 'border-transparent text-slate-400'
        "
      >
        <History class="w-4 h-4" /> 已盤紀錄
        <span
          class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1"
          >{{ scanned.length }}</span
        >
      </button>

      <!-- 盤虧異常 Tab -->
      <button
        @click="switchTab('missing')"
        class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all"
        :class="
          currentTab === 'missing'
            ? 'border-rose-500 text-rose-700 bg-rose-50/30'
            : 'border-transparent text-slate-400'
        "
      >
        <AlertCircle class="w-4 h-4" /> 盤虧異常
        <span
          class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1"
          >{{ missing.length }}</span
        >
      </button>
    </div>

    <!-- List Container -->
    <div
      ref="scrollContainer"
      class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/30 scrollbar-thin relative"
    >
      <transition-group name="list">
        <div
          v-for="item in displayedList"
          :key="item.id"
          class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center group relative overflow-hidden"
        >
          <!-- 離線標記背景 -->
          <div
            v-if="item.status === 'offline_pending'"
            class="absolute inset-0 bg-amber-50/50 pointer-events-none z-0"
          ></div>

          <!-- 左側資訊 -->
          <div class="flex items-start gap-3 z-10">
            <div class="mt-1 flex-shrink-0">
              <div
                v-if="item.status === '已盤'"
                class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center"
              >
                <Check class="w-5 h-5 text-emerald-600" />
              </div>
              <div
                v-else-if="item.status === 'offline_pending'"
                class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center animate-pulse"
              >
                <CloudOff class="w-5 h-5 text-amber-600" />
              </div>
              <div
                v-else-if="item.status === '盤虧'"
                class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center"
              >
                <HelpCircle class="w-5 h-5 text-rose-600" />
              </div>
              <div
                v-else
                class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center"
              >
                <Package class="w-5 h-5 text-amber-400" />
              </div>
            </div>

            <div>
              <div class="font-bold text-slate-800 line-clamp-1">
                {{ item.fixed_asset.name || "資料同步中..." }}
              </div>
              <div
                class="text-xs font-mono text-slate-400 flex items-center gap-2 mt-0.5"
              >
                <span class="bg-slate-100 px-1.5 rounded">{{
                  item.fixed_asset.asset_tag
                }}</span>
                <span
                  v-if="item.fixed_asset.rfid_tag"
                  class="text-indigo-400 flex items-center gap-0.5"
                >
                  <Wifi class="w-3 h-3" /> {{ item.fixed_asset.rfid_tag }}
                </span>
              </div>
              <div class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                <MapPin class="w-3 h-3" /> {{ item.fixed_asset.location || "位置未詳" }}
              </div>

              <div
                v-if="item.status === '盤虧'"
                class="text-xs text-rose-500 font-bold mt-1 flex items-center gap-1"
              >
                原因: {{ item.note }}
              </div>
            </div>
          </div>

          <!-- 右側操作按鈕 (事件傳遞與原版完全一致) -->
          <div class="z-10 text-right flex-shrink-0 ml-2">
            <button
              v-if="item.status === '未盤'"
              @click="$emit('report-missing', item)"
              class="opacity-0 group-hover:opacity-100 transition-opacity px-3 py-1.5 bg-white border border-rose-200 text-rose-600 text-xs font-bold rounded-lg hover:bg-rose-50 shadow-sm"
            >
              回報盤虧
            </button>

            <button
              v-else-if="item.status === '盤虧'"
              @click="$emit('revert-missing', item)"
              class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 text-xs font-bold rounded-lg hover:bg-slate-50 shadow-sm"
            >
              撤銷
            </button>

            <div v-else-if="item.status === '已盤'">
              <span
                class="text-emerald-600 text-xs font-bold bg-emerald-50 px-2 py-1 rounded-full border border-emerald-100"
                >已完成</span
              >
            </div>
            <div v-else-if="item.status === 'offline_pending'">
              <span
                class="text-amber-600 text-xs font-bold bg-amber-100 px-2 py-1 rounded-full border border-amber-200 flex items-center gap-1"
              >
                <RefreshCw class="w-3 h-3 animate-spin" /> 待同步
              </span>
            </div>
          </div>
        </div>
      </transition-group>

      <!-- 底部觸發點 -->
      <div
        ref="loadTrigger"
        @click="loadMore"
        class="h-14 flex items-center justify-center text-xs text-slate-400 w-full cursor-pointer hover:bg-slate-100 transition-colors"
      >
        <span
          v-if="displayedList.length < currentList.length"
          class="flex items-center gap-2"
        >
          <RefreshCw class="w-3 h-3 animate-spin text-indigo-500" />
          正在載入更多資料...
        </span>
        <span v-else class="opacity-50">已顯示全部 {{ currentList.length }} 筆項目</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from "vue";
import {
  ListFilter,
  History,
  Check,
  CloudOff,
  Package,
  Wifi,
  MapPin,
  RefreshCw,
  AlertCircle,
  HelpCircle,
} from "lucide-vue-next";

const props = defineProps(["items"]);
defineEmits(["report-missing", "revert-missing"]);

const currentTab = ref("unscanned");
const displayLimit = ref(20);
const scrollContainer = ref(null);
const loadTrigger = ref(null);
let observer = null;

const loadMore = () => {
  if (displayLimit.value < currentList.value.length) {
    displayLimit.value += 30; // 每次加 30 筆，滾動更順暢
  }
};

const switchTab = (tab) => {
  if (currentTab.value === tab) return;
  currentTab.value = tab;
  displayLimit.value = 20; // 切換 Tab 瞬間重置，保持極速切換

  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = 0;
    }
  });
};

// --- 分類邏輯 (與原版完全一致) ---
const unscanned = computed(() => props.items?.filter((i) => i.status === "未盤") || []);

const scanned = computed(
  () =>
    props.items
      ?.filter((i) => i.status === "已盤" || i.status === "offline_pending")
      .sort((a, b) => {
        const dateA = a.scanned_at ? new Date(a.scanned_at) : new Date();
        const dateB = b.scanned_at ? new Date(b.scanned_at) : new Date();
        return dateB - dateA;
      }) || []
);

const missing = computed(() => props.items?.filter((i) => i.status === "盤虧") || []);

const currentList = computed(() => {
  if (currentTab.value === "missing") return missing.value;
  if (currentTab.value === "scanned") return scanned.value;
  return unscanned.value;
});

// 只有渲染層使用這部分
const displayedList = computed(() => {
  return currentList.value.slice(0, displayLimit.value);
});

// 當 items 數量發生變化（例如剛剛掃描了一筆），檢查是否需要微調顯示數量
watch(
  () => props.items?.length,
  () => {
    // 保持顯示數量不要低於初始值，也不要大於總數
    if (displayLimit.value < 20) displayLimit.value = 20;
  },
  { deep: true }
);

onMounted(async () => {
  await nextTick();

  // 使用 Viewport Observer，這是最不會影響佈局的作法
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) {
        loadMore();
      }
    },
    {
      root: null,
      rootMargin: "150px",
      threshold: 0.01,
    }
  );

  if (loadTrigger.value) {
    observer.observe(loadTrigger.value);
  }
});

onUnmounted(() => {
  if (observer) observer.disconnect();
});
</script>

<style scoped>
.list-enter-active,
.list-leave-active {
  transition: all 0.2s ease;
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: scale(0.98);
}

.scrollbar-thin::-webkit-scrollbar {
  width: 5px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 10px;
}
</style>
