<template>
  <div
    class="flex flex-col bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-full"
  >
    <!-- 1. Tabs 切換區 -->
    <div class="flex border-b border-slate-100 bg-slate-50/50 flex-shrink-0">
      <button
        @click="switchTab('unscanned')"
        @mousedown.prevent
        class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all outline-none"
        :class="
          currentTab === 'unscanned'
            ? 'border-amber-500 text-amber-700 bg-amber-50/30'
            : 'border-transparent text-slate-400'
        "
      >
        <ListFilter class="w-4 h-4" /> 待盤點
        <span
          class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1"
          >{{ counts.unscanned }}</span
        >
      </button>

      <button
        @click="switchTab('scanned')"
        @mousedown.prevent
        class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all outline-none"
        :class="
          currentTab === 'scanned'
            ? 'border-emerald-500 text-emerald-700 bg-emerald-50/30'
            : 'border-transparent text-slate-400'
        "
      >
        <History class="w-4 h-4" /> 已盤紀錄
        <span
          class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1"
          >{{ counts.scanned }}</span
        >
      </button>

      <button
        @click="switchTab('missing')"
        @mousedown.prevent
        class="flex-1 py-4 text-sm font-bold flex items-center justify-center gap-2 border-b-2 transition-all outline-none"
        :class="
          currentTab === 'missing'
            ? 'border-rose-500 text-rose-700 bg-rose-50/30'
            : 'border-transparent text-slate-400'
        "
      >
        <AlertCircle class="w-4 h-4" /> 盤虧異常
        <span
          class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px] ml-1"
          >{{ counts.missing }}</span
        >
      </button>
    </div>

    <!-- 2. 滾動列表區 -->
    <div
      ref="scrollContainer"
      @scroll="onScroll"
      class="flex-1 overflow-y-auto bg-slate-50/30 scrollbar-thin relative p-4"
    >
      <!-- A. 待盤點模式 (分組顯示 - 手風琴模式) -->
      <div v-if="currentTab === 'unscanned'" class="space-y-4">
        <div v-for="group in visibleGroups" :key="group.key" class="bg-transparent">
          <!-- 區域 Header -->
          <!-- 
             ★ 修正：
             1. 移除 backdrop-blur-sm (解決隱形問題的根源)
             2. 改用 bg-slate-100 (實色背景，效能最好)
             3. 保持 @mousedown.prevent (解決跳動問題)
          -->
          <div
            @click.stop="toggleGroup(group.key)"
            @mousedown.prevent
            class="sticky top-0 z-10 bg-slate-100 py-3 px-2 flex items-center justify-between border-b border-slate-200/60 cursor-pointer select-none group/header hover:bg-slate-200 transition-colors rounded-lg shadow-sm"
          >
            <div class="flex items-center gap-3">
              <div
                class="p-1 rounded-md bg-white border border-slate-200 shadow-sm text-slate-400 group-hover/header:text-slate-600 group-hover/header:border-slate-300 transition-all"
                :class="
                  expandedGroupKey === group.key ? 'text-amber-500 border-amber-200' : ''
                "
              >
                <!-- 判斷當前展開的 Key 是否為自己 -->
                <component
                  :is="expandedGroupKey === group.key ? ChevronDown : ChevronRight"
                  class="w-3.5 h-3.5"
                />
              </div>

              <div class="flex flex-col text-left">
                <div class="flex items-center gap-2">
                  <span
                    class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter bg-slate-200 px-1.5 rounded-sm"
                  >
                    {{ group.factory }}<span v-if="group.floor">-{{ group.floor }}</span>
                  </span>
                  <!-- 如果沒展開，顯示一個小點提示 -->
                  <div
                    class="w-1.5 h-1.5 bg-amber-500 rounded-full"
                    v-if="expandedGroupKey !== group.key"
                  ></div>
                </div>
                <span class="text-sm font-black text-slate-700 tracking-wider mt-0.5">{{
                  group.areaName
                }}</span>
              </div>
            </div>

            <div
              class="text-[10px] font-bold text-amber-600 bg-white px-2.5 py-1 rounded-full border border-amber-100 shadow-sm"
            >
              {{ group.items.length }} 筆
            </div>
          </div>

          <!-- 區域內容：只在 expandedGroupKey 與自己相符時顯示 -->
          <div v-show="expandedGroupKey === group.key" class="pt-2 pl-2 space-y-3 pb-4">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-start hover:shadow-md transition-shadow"
            >
              <div class="flex items-start gap-3 text-left">
                <div class="mt-1 flex-shrink-0">
                  <div
                    class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center"
                  >
                    <Package class="w-4 h-4 text-slate-400" />
                  </div>
                </div>
                <div>
                  <div class="font-bold text-slate-800 line-clamp-1 text-sm">
                    {{ item.fixed_asset.name }}
                  </div>
                  <div
                    class="text-[10px] font-mono text-slate-400 mt-0.5 flex items-center gap-2"
                  >
                    <span class="bg-slate-100 px-1 rounded border border-slate-200">{{
                      item.fixed_asset.asset_tag
                    }}</span>
                    <span
                      v-if="item.fixed_asset.location"
                      class="flex items-center gap-0.5"
                    >
                      <MapPin class="w-3 h-3" /> {{ item.fixed_asset.location }}
                    </span>
                  </div>
                </div>
              </div>

              <button
                @click.stop="$emit('report-missing', item)"
                @mousedown.prevent
                class="px-3 py-1.5 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100 hover:bg-rose-100 transition-colors"
              >
                報失
              </button>
            </div>
          </div>
        </div>

        <!-- 狀態顯示 -->
        <div class="py-12 text-center text-slate-400">
          <div v-if="allGroups.length === 0" class="flex flex-col items-center gap-3">
            <div
              class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center"
            >
              <Check class="w-6 h-6" />
            </div>
            <p class="text-sm font-medium text-slate-500">所有資產已完成盤點！</p>
          </div>

          <!-- 手動載入按鈕 -->
          <div
            v-else-if="visibleGroups.length < allGroups.length"
            @click="loadMore"
            class="flex items-center justify-center gap-2 text-xs py-4 cursor-pointer hover:text-amber-600 transition-colors select-none"
          >
            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
            <span>載入更多區域中... (若卡住請點擊)</span>
          </div>

          <div
            v-else
            class="text-[10px] font-bold opacity-30 uppercase tracking-widest pt-8 pb-4"
          >
            — 已顯示全部資料 —
          </div>
        </div>
      </div>

      <!-- B. 其他模式 (已盤/盤虧) -->
      <div v-else class="space-y-3">
        <div
          v-for="item in visibleFlatList"
          :key="item.id"
          class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center relative overflow-hidden"
        >
          <div
            v-if="item.status === 'offline_pending'"
            class="absolute inset-0 bg-amber-50/40 pointer-events-none"
          ></div>

          <div class="flex items-start gap-3 z-10 text-left">
            <div class="mt-1 flex-shrink-0">
              <div
                v-if="item.status === '已盤'"
                class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center"
              >
                <Check class="w-4 h-4 text-emerald-600" />
              </div>
              <div
                v-else-if="item.status === '盤虧'"
                class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center"
              >
                <HelpCircle class="w-4 h-4 text-rose-600" />
              </div>
              <div
                v-else
                class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center animate-pulse"
              >
                <CloudOff class="w-4 h-4 text-amber-600" />
              </div>
            </div>
            <div>
              <div class="font-bold text-slate-800 line-clamp-1 text-sm">
                {{ item.fixed_asset.name }}
              </div>
              <div class="text-[10px] text-slate-400 mt-0.5">
                {{ item.fixed_asset.asset_tag }}
              </div>
              <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1 italic">
                {{ item.fixed_asset.factory_area }} / {{ item.fixed_asset.area }}
              </div>
            </div>
          </div>

          <div class="z-10">
            <button
              v-if="item.status === '盤虧'"
              @click="$emit('revert-missing', item)"
              @mousedown.prevent
              class="px-2.5 py-1.5 bg-white border border-slate-200 text-slate-500 text-[10px] font-bold rounded-lg hover:bg-slate-50"
            >
              撤銷
            </button>
            <span
              v-else-if="item.status === '已盤'"
              class="text-emerald-600 text-[10px] font-bold bg-emerald-50 px-2 py-1 rounded-full border border-emerald-100"
              >已盤點</span
            >
            <span
              v-else
              class="text-amber-600 text-[10px] font-bold bg-amber-100 px-2 py-1 rounded-full border border-amber-200 flex items-center gap-1"
              ><RefreshCw class="w-3 h-3 animate-spin" /> 待同步</span
            >
          </div>
        </div>

        <div
          v-if="visibleFlatList.length < currentFlatList.length"
          @click="loadMore"
          class="py-4 text-center flex items-center justify-center gap-2 text-xs text-slate-400 cursor-pointer hover:text-indigo-600 transition-colors select-none"
        >
          <RefreshCw class="w-3 h-3 animate-spin" /> 載入更多... (若卡住請點擊)
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, reactive, nextTick, onMounted } from "vue";
import {
  ListFilter,
  History,
  AlertCircle,
  Package,
  MapPin,
  ChevronDown,
  ChevronRight,
  Check,
  RefreshCw,
  HelpCircle,
  CloudOff,
} from "lucide-vue-next";

const props = defineProps(["items"]);
defineEmits(["report-missing", "revert-missing"]);

// 1. 基礎狀態
const currentTab = ref("unscanned");
const expandedGroupKey = ref(null);
const limit = ref(10);
const scrollContainer = ref(null);

// 2. 資料分類
const unscannedItems = computed(
  () => props.items?.filter((i) => i.status === "未盤") || []
);
const scannedItems = computed(
  () =>
    props.items
      ?.filter((i) => i.status === "已盤" || i.status === "offline_pending")
      .sort((a, b) => new Date(b.scanned_at) - new Date(a.scanned_at)) || []
);
const missingItems = computed(
  () => props.items?.filter((i) => i.status === "盤虧") || []
);

const counts = computed(() => ({
  unscanned: unscannedItems.value.length,
  scanned: scannedItems.value.length,
  missing: missingItems.value.length,
}));

// 3. 分組邏輯
const allGroups = computed(() => {
  const map = new Map();
  unscannedItems.value.forEach((item) => {
    const f = item.fixed_asset.factory_area || "未知";
    const fl = item.fixed_asset.floor || "";
    const a = item.fixed_asset.area || "未分類";
    const key = `${f}__${fl}__${a}`;

    if (!map.has(key)) {
      map.set(key, { key, factory: f, floor: fl, areaName: a, items: [] });
    }
    map.get(key).items.push(item);
  });
  return Array.from(map.values()).sort((a, b) => a.key.localeCompare(b.key));
});

const currentFlatList = computed(() => {
  if (currentTab.value === "scanned") return scannedItems.value;
  if (currentTab.value === "missing") return missingItems.value;
  return [];
});

// 4. 分頁顯示
const visibleGroups = computed(() => allGroups.value.slice(0, limit.value));
const visibleFlatList = computed(() => currentFlatList.value.slice(0, limit.value * 2));

// 6. 滾動加載 & 自動填滿
const onScroll = (e) => {
  const { scrollTop, scrollHeight, clientHeight } = e.target;
  if (scrollTop + clientHeight >= scrollHeight - 200) {
    loadMore();
  }
};

const loadMore = () => {
  const total =
    currentTab.value === "unscanned"
      ? allGroups.value.length
      : currentFlatList.value.length;
  if (limit.value < total) {
    limit.value += 10;
    checkAndFill();
  }
};

// 遞歸檢查：如果內容高度不足以產生捲軸，就自動再載入下一頁
const checkAndFill = async () => {
  await nextTick();
  if (!scrollContainer.value) return;

  const { scrollHeight, clientHeight } = scrollContainer.value;
  const total =
    currentTab.value === "unscanned"
      ? allGroups.value.length
      : currentFlatList.value.length;

  if (limit.value < total && scrollHeight <= clientHeight + 50) {
    setTimeout(() => {
      loadMore();
    }, 50);
  }
};

// 7. 手風琴模式切換 (單一展開)
const toggleGroup = (key) => {
  if (expandedGroupKey.value === key) {
    expandedGroupKey.value = null;
  } else {
    expandedGroupKey.value = key;
  }

  // 展開/關閉後高度變化大，檢查是否需要補標題資料
  checkAndFill();
};

const switchTab = (tab) => {
  currentTab.value = tab;
  limit.value = 10;
  expandedGroupKey.value = null;
  nextTick(() => {
    if (scrollContainer.value) scrollContainer.value.scrollTop = 0;
    checkAndFill();
  });
};

watch(
  () => props.items,
  () => {
    checkAndFill();
  },
  { deep: true }
);

onMounted(() => {
  checkAndFill();
});
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 4px;
}
</style>
