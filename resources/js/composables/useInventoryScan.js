import { ref, watch } from 'vue';
import axios from 'axios';

export function useInventoryScan(workOrderId, orderRef, isOnlineRef, pendingSyncQueueRef) {
    const scanInput = ref(null);
    const displayValue = ref('');
    const scanBuffer = ref('');
    const scanQueue = ref([]);
    const isProcessing = ref(false);
    const inputWarning = ref(false);

    // UI 狀態訊息
    const msg = ref('');
    const msgType = ref('');
    const scannedAssetInfo = ref('');

    const batchTimer = ref(null);
    let lastKeyTime = 0;

    // ★★★ 參數調校 (解決漏掃關鍵) ★★★
    // 1. 放寬手動輸入判定 (避免電腦卡頓時誤刪資料)
    const SCAN_SPEED_THRESHOLD = 200;
    // 2. 批次等待時間 (蒐集更多條碼再一次送出)
    const BATCH_WAIT_TIME = 700;

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

    const handleKeydown = (e) => {
        const now = Date.now();
        const code = e.code;
        const timeDiff = now - lastKeyTime;
        lastKeyTime = now;

        // Debug: 監控按鍵速度 (如果不確定是否被誤判為手動輸入，可開啟此行)
        // console.log(`Key: ${code}, Diff: ${timeDiff}ms`);

        if (code === 'Enter' || code === 'NumpadEnter') {
            e.preventDefault();
            if (scanBuffer.value.length > 0) {
                if (timeDiff > SCAN_SPEED_THRESHOLD) {
                    console.warn(`⚠️ 偵測到手動輸入 (間隔 ${timeDiff}ms > ${SCAN_SPEED_THRESHOLD}ms)，已阻擋:`, scanBuffer.value);
                    resetBufferWithWarning();
                    return;
                }
                scanQueue.value.push(scanBuffer.value);
                console.log('📥 加入佇列:', scanBuffer.value);
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
            e.preventDefault();

            if (scanBuffer.value.length > 0 && timeDiff > SCAN_SPEED_THRESHOLD) {
                console.warn(`⚠️ 偵測到手動輸入 (間隔 ${timeDiff}ms)，已阻擋`);
                resetBufferWithWarning();
                return;
            }
            inputWarning.value = false;
            scanBuffer.value += char.toUpperCase();

            if (!isProcessing.value) {
                displayValue.value = scanBuffer.value;
            }
        }
    };

    const resetBufferWithWarning = () => {
        scanBuffer.value = '';
        displayValue.value = '';
        inputWarning.value = true;
        setTimeout(() => inputWarning.value = false, 1500);
    };

    // 監聽 Queue 並防抖
    watch(() => scanQueue.value.length, (newLength) => {
        if (newLength > 0) {
            if (batchTimer.value) clearTimeout(batchTimer.value);
            // 每次有新輸入就重新計時，直到 500ms 內沒有新輸入才送出
            batchTimer.value = setTimeout(processBatchQueue, BATCH_WAIT_TIME);
        }
    });

    // ★★★ 重點修改區域：processBatchQueue ★★★
    const processBatchQueue = async () => {
        if (scanQueue.value.length === 0) {
            isProcessing.value = false;
            return;
        }

        isProcessing.value = true;
        const currentBatch = scanQueue.value.splice(0, scanQueue.value.length);
        displayValue.value = `處理中...`;

        // 離線模式邏輯 (簡單過濾雜訊)
        if (!isOnlineRef.value) {
            let successCount = 0;
            currentBatch.forEach(tag => {
                const idx = orderRef.value.items.findIndex(i => i.fixed_asset?.rfid_tag === tag);
                if (idx !== -1) {
                    const targetItem = orderRef.value.items[idx];
                    if (targetItem.status !== '已盤' && targetItem.status !== 'offline_pending') {
                        targetItem.status = 'offline_pending';
                        targetItem.scanned_at = new Date().toISOString();
                        pendingSyncQueueRef.value.push(tag);
                        successCount++;
                    }
                }
            });

            if (successCount > 0) {
                msg.value = `離線暫存: ${successCount} 筆`;
                msgType.value = 'offline';
                scannedAssetInfo.value = `批次包含 ${currentBatch.length} 個標籤`;
            } else {
                // ★ 離線模式下，如果沒對應到，視為雜訊 (Neutral)
                msg.value = '未偵測到本單資產';
                msgType.value = 'neutral'; // 新增 neutral 類型
                scannedAssetInfo.value = `忽略 ${currentBatch.length} 個無關訊號`;
            }

            isProcessing.value = false;
            if (scanQueue.value.length > 0) processBatchQueue();
            else setTimeout(() => { if (displayValue.value.includes('處理')) displayValue.value = ''; }, 800);
            return;
        }

        // 線上模式邏輯
        try {
            const res = await axios.post(`/api/inventory/work-orders/${workOrderId}/batch-scan`, {
                rfid_tags: currentBatch
            });

            const { success, failed } = res.data;

            // 更新成功項目
            success.forEach(updatedItem => {
                const idx = orderRef.value.items.findIndex(i => i.id === updatedItem.id);
                if (idx !== -1) orderRef.value.items[idx] = updatedItem;
            });

            // ★ 區分「真實錯誤」與「無關雜訊」
            const noiseReasons = ['非本單資產', '無效的標籤', '未知標籤'];
            const realErrors = failed.filter(f => !noiseReasons.includes(f.reason));
            const noiseCount = failed.length - realErrors.length;

            if (success.length > 0) {
                // 情境 A: 有成功 (無論有沒有雜訊，都顯示成功綠色)
                msg.value = `批次成功: ${success.length} 筆`;
                msgType.value = 'success';

                // 如果有雜訊，只在小字提示，不干擾主訊息
                if (noiseCount > 0) {
                    scannedAssetInfo.value = `(已過濾 ${noiseCount} 個無關訊號)`;
                } else {
                    scannedAssetInfo.value = '';
                }

            } else {
                // 情境 B: 完全沒有成功
                if (realErrors.length > 0) {
                    // 有真實錯誤 (如資料庫錯誤)，才顯示紅色
                    msg.value = `系統錯誤: ${realErrors[0].reason}`;
                    msgType.value = 'error';
                } else {
                    // 全都是無關雜訊 -> 顯示淡灰色 (Neutral)
                    msg.value = '未偵測到本單資產';
                    msgType.value = 'neutral';
                    scannedAssetInfo.value = `忽略 ${noiseCount} 個無關訊號`;
                }
            }

        } catch (e) {
            console.error(e);
            msg.value = '連線失敗';
            msgType.value = 'error';
        } finally {
            finishProcessing();
        }
    };

    // 統一的結束處理函式，負責調度下一次批次
    const finishProcessing = () => {
        isProcessing.value = false;

        // 🔥【關鍵修正】🔥
        // 檢查佇列是否還有新資料
        if (scanQueue.value.length > 0) {
            // 重要：即使有資料，也不要立刻執行！
            // 而是重新設定 Timer，強制等待 BATCH_WAIT_TIME
            // 這樣可以把處理期間累積的 1-2 筆資料，與接下來 700ms 內掃到的資料合併
            // 徹底避免 "一有資料就送" 的高頻請求
            console.log(`📥 佇列還有 ${scanQueue.value.length} 筆，重新倒數 ${BATCH_WAIT_TIME}ms 後發送`);

            if (batchTimer.value) clearTimeout(batchTimer.value);
            batchTimer.value = setTimeout(processBatchQueue, BATCH_WAIT_TIME);
        } else {
            // 佇列空了，清除 UI 上的 "處理中..." 文字
            setTimeout(() => {
                if (displayValue.value.includes('處理')) displayValue.value = '';
            }, 800);
        }
    };

    const focusInput = () => {
        scanInput.value?.focus();
    };

    // ★ 新增：供 USB 離線批次匯入直接推進佇列並立即送出核對
    const enqueueBatchTags = async (tagsArray) => {
        if (!Array.isArray(tagsArray) || tagsArray.length === 0) return;
        const cleaned = tagsArray
            .map(t => String(t || '').trim().toUpperCase())
            .filter(Boolean);
        if (cleaned.length === 0) return;

        if (batchTimer.value) {
            clearTimeout(batchTimer.value);
            batchTimer.value = null;
        }
        scanQueue.value.push(...cleaned);
        if (!isProcessing.value) {
            await processBatchQueue();
        }
    };

    return {
        scanInput,
        displayValue,
        scanQueue,
        isProcessing,
        inputWarning,
        msg,
        msgType,
        scannedAssetInfo,
        handleKeydown,
        focusInput,
        enqueueBatchTags
    };
}