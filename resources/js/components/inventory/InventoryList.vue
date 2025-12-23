<template>
  <div
    class="flex flex-col bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-full"
  >
    <!-- Tabs -->
    <div class="flex border-b border-slate-100 bg-slate-50/50 flex-shrink-0">
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
      class="flex-1 overflow-y-auto bg-slate-50/30 scrollbar-thin relative p-4"
    >
      <!-- A. 待盤點模式：區域分組 + 分段加載 -->
      <div v-if="currentTab === 'unscanned'" class="space-y-8">
        <div v-for="group in displayedAreas" :key="group.key" class="space-y-3">
          <!-- 區域 Header -->
          <div
            class="sticky top-0 z-20 bg-slate-50/95 backdrop-blur-sm py-2 flex items-center justify-between border-b border-slate-200/50 mb-4"
          >
            <div class="flex items-center gap-2">
              <div class="w-1.5 h-5 bg-amber-500 rounded-full"></div>
              <div class="flex flex-col text-left">
                <span
                  class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter"
                >
                  {{ group.factory }} <span v-if="group.floor">/ {{ group.floor }}</span>
                </span>
                <span class="text-sm font-black text-slate-700 tracking-wider">{{
                  group.areaName
                }}</span>
              </div>
            </div>
            <div
              class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded border border-amber-100 shadow-sm"
            >
              尚餘 {{ group.count }} 件
            </div>
          </div>

          <!-- 區域內項目 -->
          <div class="space-y-3">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center group"
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
                    <span class="bg-slate-100 px-1 rounded">{{
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
                @click="$emit('report-missing', item)"
                class="sm:opacity-0 group-hover:opacity-100 transition-all px-2.5 py-1.5 bg-rose-50 text-rose-600 text-[10px] font-bold rounded-lg border border-rose-100 hover:bg-rose-100"
              >
                報失
              </button>
            </div>
          </div>
        </div>

        <!-- 空狀態 -->
        <div v-if="unscanned.length === 0" class="py-20 text-center space-y-3">
          <div
            class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto"
          >
            <Check class="w-8 h-8" />
          </div>
          <p class="text-slate-400 text-sm font-medium">恭喜！本工單所有資產已盤點完成</p>
        </div>

        <!-- 加載觸發器 (使用 root: null 監聽 Viewport) -->
        <div
          ref="loadTrigger"
          @click="loadMore"
          class="h-24 flex flex-col items-center justify-center text-slate-400 text-xs gap-2 cursor-pointer hover:bg-slate-100/50 rounded-lg transition-colors"
        >
          <template
            v-if="
              currentTab === 'unscanned' &&
              areaLimit < Object.keys(groupedUnscanned).length
            "
          >
            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
            <span>正在載入更多區域... (點擊手動載入)</span>
          </template>
          <template v-else-if="unscanned.length > 0">
            <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
            <span class="opacity-40 font-bold tracking-widest uppercase"
              >已顯示所有區域</span
            >
          </template>
        </div>
      </div>

      <!-- B. 已盤/盤虧模式 -->
      <div v-else class="space-y-3">
        <div
          v-for="item in displayedList"
          :key="item.id"
          class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center group relative overflow-hidden"
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
                v-else-if="item.status === 'offline_pending'"
                class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center animate-pulse"
              >
                <CloudOff class="w-4 h-4 text-amber-600" />
              </div>
              <div
                v-else-if="item.status === '盤虧'"
                class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center"
              >
                <HelpCircle class="w-4 h-4 text-rose-600" />
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
                {{ item.fixed_asset.factory_area }} <span class="text-slate-300">/</span>
                {{ item.fixed_asset.floor }} <span class="text-slate-300">/</span>
                {{ item.fixed_asset.area }}
              </div>
            </div>
          </div>

          <div class="z-10">
            <button
              v-if="item.status === '盤虧'"
              @click="$emit('revert-missing', item)"
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
              v-else-if="item.status === 'offline_pending'"
              class="text-amber-600 text-[10px] font-bold bg-amber-100 px-2 py-1 rounded-full border border-amber-200 flex items-center gap-1"
              ><RefreshCw class="w-3 h-3 animate-spin" /> 待同步</span
            >
          </div>
        </div>

        <div
          ref="loadTrigger"
          @click="loadMore"
          class="h-20 flex items-center justify-center text-[10px] text-slate-400 cursor-pointer hover:bg-slate-100/50 rounded-lg"
        >
          <span
            v-if="displayedList.length < currentList.length"
            class="flex items-center gap-2"
          >
            <RefreshCw class="w-3 h-3 animate-spin text-indigo-400" /> 加載中
            (點擊手動載入)...
          </span>
          <span v-else-if="currentList.length > 0" class="opacity-50"
            >已顯示全部 {{ currentList.length }} 筆項目</span
          >
        </div>
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
  MapPin,
  RefreshCw,
  AlertCircle,
  HelpCircle,
} from "lucide-vue-next";

const props = defineProps(["items"]);
defineEmits(["report-missing", "revert-missing"]);

const currentTab = ref("unscanned");
const displayLimit = ref(20);
const areaLimit = ref(10); // ★ 增加初始加載數量至 10，確保填滿畫面
const scrollContainer = ref(null);
const loadTrigger = ref(null);
let observer = null;

const loadMore = () => {
  if (currentTab.value === "unscanned") {
    const totalAreas = Object.keys(groupedUnscanned.value).length;
    if (areaLimit.value < totalAreas) {
      areaLimit.value += 10; // ★ 每次增加 10 個區域
    }
  } else {
    if (displayLimit.value < currentList.value.length) {
      displayLimit.value += 30;
    }
  }
};

const switchTab = (tab) => {
  if (currentTab.value === tab) return;
  currentTab.value = tab;
  displayLimit.value = 20;
  areaLimit.value = 10;
  nextTick(() => {
    if (scrollContainer.value) scrollContainer.value.scrollTop = 0;
  });
};

// --- 分類與分組 ---
const unscanned = computed(() => props.items?.filter((i) => i.status === "未盤") || []);
const scanned = computed(
  () =>
    props.items
      ?.filter((i) => i.status === "已盤" || i.status === "offline_pending")
      .sort(
        (a, b) =>
          (b.scanned_at ? new Date(b.scanned_at) : new Date()) -
          (a.scanned_at ? new Date(a.scanned_at) : new Date())
      ) || []
);
const missing = computed(() => props.items?.filter((i) => i.status === "盤虧") || []);

const groupedUnscanned = computed(() => {
  const groups = {};
  unscanned.value.forEach((item) => {
    const f = item.fixed_asset.factory_area || "未知廠區";
    const fl = item.fixed_asset.floor || "?";
    const a = item.fixed_asset.area || "未分類";
    const groupKey = `${f}|${fl}|${a}`;
    if (!groups[groupKey]) {
      groups[groupKey] = { factory: f, floor: fl, areaName: a, items: [] };
    }
    groups[groupKey].items.push(item);
  });
  return groups;
});

const displayedAreas = computed(() => {
  const allGroupKeys = Object.keys(groupedUnscanned.value).sort();
  const limitedKeys = allGroupKeys.slice(0, areaLimit.value);
  return limitedKeys.map((key) => ({
    key,
    ...groupedUnscanned.value[key],
    count: groupedUnscanned.value[key].items.length,
  }));
});

const currentList = computed(() => {
  if (currentTab.value === "missing") return missing.value;
  if (currentTab.value === "scanned") return scanned.value;
  return unscanned.value;
});

const displayedList = computed(() => currentList.value.slice(0, displayLimit.value));

const initObserver = () => {
  if (observer) observer.disconnect();
  if (typeof window !== "undefined" && "IntersectionObserver" in window) {
    observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          loadMore();
        }
      },
      {
        root: null, // ★ 關鍵修改：使用 Viewport 作為基準，比指定容器更穩定
        rootMargin: "300px", // 提前觸發
        threshold: 0,
      }
    );
    if (loadTrigger.value) observer.observe(loadTrigger.value);
  }
};

onMounted(async () => {
  await nextTick();
  initObserver();
});

// 當 Tab 切換時，可能需要重新觀察新的 loadTrigger
watch(currentTab, async () => {
  await nextTick();
  initObserver();
});

onUnmounted(() => {
  if (observer) observer.disconnect();
});
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 10px;
}
.sticky {
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.01);
}
</style>
