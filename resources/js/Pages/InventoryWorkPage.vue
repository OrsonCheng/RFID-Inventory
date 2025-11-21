<template>
    <div class="container mx-auto p-6 bg-gray-50 min-h-screen">
        <div v-if="loading" class="text-center text-xl">工單加載中...</div>
        <div v-else-if="!workOrder" class="text-center text-xl text-red-500">工單加載失敗</div>
        <div v-else>
            <!-- 1. 表頭 -->
            <h1 class="text-3xl font-bold mb-2 text-gray-800">{{ workOrder.title }}</h1>
            <p class="text-lg text-gray-600 mb-4">
                保管人: <span class="font-semibold">{{ workOrder.responsible_user?.name }}</span>
            </p>

            <!-- 2. RFID 掃描輸入框 (核心) -->
            <div class="mb-6">
                <label for="rfid-input" class="block text-sm font-medium text-gray-700 mb-1">RFID 掃描 (掃描器將自動輸入並 Enter)</label>
                <input 
                    id="rfid-input"
                    ref="rfidInputRef" 
                    v-model="rfidInput"
                    @keyup.enter="handleScan"
                    :disabled="isScanning"
                    placeholder="請在此處掃描 RFID..."
                    class="w-full p-3 text-lg border-2 border-blue-500 rounded-lg shadow-sm focus:ring-blue-700 focus:border-blue-700"
                >
                <!-- 掃描反饋 -->
                <div v-if="scanMessage" :class="scanMessageClass" class="mt-2 p-3 rounded-md text-sm">
                    {{ scanMessage }}
                </div>
            </div>

            <!-- 3. 統計數據 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-100 border border-blue-200 p-4 rounded-lg shadow-sm">
                    <div class="text-sm font-medium text-blue-700">應盤總數</div>
                    <div class="text-3xl font-bold text-blue-900">{{ totalItems }}</div>
                </div>
                <div class="bg-green-100 border border-green-200 p-4 rounded-lg shadow-sm">
                    <div class="text-sm font-medium text-green-700">已盤數量</div>
                    <div class="text-3xl font-bold text-green-900">{{ scannedItems.length }}</div>
                </div>
                <div class="bg-yellow-100 border border-yellow-300 p-4 rounded-lg shadow-sm">
                    <div class="text-sm font-medium text-yellow-700">未盤數量</div>
                    <div class="text-3xl font-bold text-yellow-900">{{ unscannedItems.length }}</div>
                </div>
            </div>

            <!-- 4. 列表 (未盤 / 已盤) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- 未盤列表 -->
                <div>
                    <h2 class="text-2xl font-semibold mb-3 text-yellow-800">未盤列表 ({{ unscannedItems.length }})</h2>
                    <div class="bg-white shadow-md rounded-lg overflow-hidden max-h-[60vh] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-100 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">固資編號</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">設備名稱</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">存放位置</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="item in unscannedItems" :key="item.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.fixed_asset?.asset_tag }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ item.fixed_asset?.name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ item.fixed_asset?.location }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 已盤列表 -->
                <div>
                    <h2 class="text-2xl font-semibold mb-3 text-green-800">已盤列表 ({{ scannedItems.length }})</h2>
                    <div class="bg-white shadow-md rounded-lg overflow-hidden max-h-[60vh] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">固資編號</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">設備名稱</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">掃描時間</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="item in scannedItems" :key="item.id" class="bg-green-50 hover:bg-green-100">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.fixed_asset?.asset_tag }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ item.fixed_asset?.name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ new Date(item.scanned_at).toLocaleTimeString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import axios from 'axios';

// 接收來自路由的 props
const props = defineProps({
    id: {
        type: String,
        required: true,
    }
});

// 狀態
const workOrder = ref(null);
const loading = ref(false);
const rfidInput = ref('');
const rfidInputRef = ref(null); // 用於 focus
const isScanning = ref(false);
const scanMessage = ref('');
const scanMessageClass = ref(''); // 'text-green-700 bg-green-100', 'text-red-700 bg-red-100'

// 計算屬性
const allItems = computed(() => workOrder.value?.items || []);
const totalItems = computed(() => allItems.value.length);
const scannedItems = computed(() => allItems.value.filter(item => item.status === '已盤').sort((a,b) => new Date(b.scanned_at) - new Date(a.scanned_at)));
const unscannedItems = computed(() => allItems.value.filter(item => item.status === '未盤'));

// 獲取工單詳情
async function fetchWorkOrderDetails() {
    loading.value = true;
    try {
        const response = await axios.get(`/api/inventory/work-orders/${props.id}`);
        workOrder.value = response.data;
    } catch (error) {
        console.error('獲取工單詳情失敗:', error);
        workOrder.value = null; // 標記為加載失敗
    } finally {
        loading.value = false;
        // 加載完成後，自動 focus 輸入框
        focusInput();
    }
}

// 處理掃描
async function handleScan() {
    if (!rfidInput.value || isScanning.value) return;

    isScanning.value = true;
    const rfidToScan = rfidInput.value;
    rfidInput.value = ''; // 立即清除輸入框

    try {
        const response = await axios.post(`/api/inventory/work-orders/${props.id}/scan`, {
            rfid_tag: rfidToScan
        });

        // 掃描成功
        const updatedItem = response.data;
        playBeep(true);
        setScanMessage(`成功： [${updatedItem.fixed_asset.name}] 已盤點。`, 'success');

        // 更新前端狀態
        const index = allItems.value.findIndex(item => item.id === updatedItem.id);
        if (index !== -1) {
            workOrder.value.items[index] = updatedItem;
        }

    } catch (error) {
        // 掃描失敗
        playBeep(false);
        const errorMessage = error.response?.data?.message || '掃描失敗，未知錯誤';
        setScanMessage(`失敗 (RFID: ${rfidToScan}): ${errorMessage}`, 'error');
    } finally {
        isScanning.value = false;
        focusInput();
    }
}

// 輔助函式：設置反饋消息
function setScanMessage(message, type) {
    scanMessage.value = message;
    if (type === 'success') {
        scanMessageClass.value = 'text-green-700 bg-green-100 border border-green-200';
    } else {
        scanMessageClass.value = 'text-red-700 bg-red-100 border border-red-200';
    }
    // 5秒後清除消息
    setTimeout(() => {
        scanMessage.value = '';
    }, 5000);
}

// 輔助函式：Focus 輸入框
function focusInput() {
    nextTick(() => {
        rfidInputRef.value?.focus();
    });
}

// 輔助函式：播放提示音 (可選)
function playBeep(success = true) {
    // 這裡可以使用 Tone.js 或 Web Audio API 來播放聲音
    // 簡單演示，您可以引入 Tone.js
    // if (success) {
    //     const synth = new Tone.Synth().toDestination();
    //     synth.triggerAttackRelease("C5", "8n");
    // } else {
    //     const synth = new Tone.Synth().toDestination();
    //     synth.triggerAttackRelease("C3", "8n");
    // }
    console.log(success ? 'BEEP_SUCCESS' : 'BEEP_ERROR');
}


// 初始化
onMounted(() => {
    fetchWorkOrderDetails();
});
</script>