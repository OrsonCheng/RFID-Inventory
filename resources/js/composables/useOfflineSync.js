import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

export function useOfflineSync(workOrderId, orderRef) {
    const isOnline = ref(navigator.onLine);
    const isSimulatedOffline = ref(false); // Debug 用
    const pendingSyncQueue = ref([]);
    const isSyncing = ref(false);
    const syncMsg = ref('');

    // 實際的連線狀態 (考慮模擬斷網)
    const effectiveIsOnline = computed(() => {
        return isOnline.value && !isSimulatedOffline.value;
    });

    const updateOnlineStatus = () => {
        isOnline.value = navigator.onLine;
        if (effectiveIsOnline.value && pendingSyncQueue.value.length > 0) {
            syncOfflineData();
        }
    };

    const toggleDebugOffline = () => {
        isSimulatedOffline.value = !isSimulatedOffline.value;
        updateOnlineStatus(); // 觸發狀態更新
    };

    // 同步邏輯 (維持單筆同步以確保穩定性，或視需求改批次)
    const syncOfflineData = async () => {
        if (isSyncing.value || pendingSyncQueue.value.length === 0) return;
        if (!effectiveIsOnline.value) return;

        isSyncing.value = true;

        const processNext = async () => {
            if (pendingSyncQueue.value.length === 0) {
                isSyncing.value = false;
                syncMsg.value = '所有離線資料同步完成！';
                setTimeout(() => syncMsg.value = '', 3000);
                return;
            }

            const rfid = pendingSyncQueue.value[0];

            try {
                // 這裡使用單筆掃描接口進行補上傳
                const res = await axios.post(`/api/inventory/work-orders/${workOrderId}/scan`, { 
                    rfid_tag: rfid 
                });

                // 更新 Order 狀態
                const idx = orderRef.value.items.findIndex(i => i.fixed_asset?.rfid_tag === rfid);
                if (idx !== -1) {
                    orderRef.value.items[idx] = res.data;
                }

                pendingSyncQueue.value.shift(); // 移除已處理
                setTimeout(processNext, 100);   // 處理下一筆

            } catch (e) {
                // 如果是 404/422 (無效標籤)，也視為已處理(移除)，並將狀態改回未盤
                if (e.response && (e.response.status === 404 || e.response.status === 422)) {
                     pendingSyncQueue.value.shift();
                     const idx = orderRef.value.items.findIndex(i => i.fixed_asset?.rfid_tag === rfid);
                     if (idx !== -1) orderRef.value.items[idx].status = '未盤'; // 或標記為錯誤
                     setTimeout(processNext, 100);
                } else {
                    // 網路錯誤，中斷同步
                    isSyncing.value = false;
                }
            }
        };

        processNext();
    };

    onMounted(() => {
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
    });

    onUnmounted(() => {
        window.removeEventListener('online', updateOnlineStatus);
        window.removeEventListener('offline', updateOnlineStatus);
    });

    return {
        isOnline: effectiveIsOnline,
        isSimulatedOffline,
        pendingSyncQueue,
        isSyncing,
        syncMsg,
        toggleDebugOffline,
        syncOfflineData
    };
}