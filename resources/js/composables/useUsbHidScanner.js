import { ref, onMounted, onUnmounted } from 'vue';

/**
 * UR-94E4 (Chainway / WYUAN R2) WebHID 離線批次匯入模組
 * VID: 0x2047 (Texas Instruments MSP430), PID: 0x0301
 *
 * 支援協定 (逆向自 UHFAPI.dll):
 * - 讀取槍內暫存標籤數量: A5 5A 00 09 E9 00 E0 0D 0A -> 回應 0xEA [CountH] [CountL]
 * - 批次分頁匯出暫存標籤: A5 5A 00 08 EB E3 0D 0A    -> 回應 0xEC [TagCount] [Len][EPC...]...
 * - 清空槍內所有暫存標籤: A5 5A 00 09 E9 FF 1F 0D 0A -> 回應 0xEA 00 (或 0xEA 00 00)
 */
export function useUsbHidScanner(onBatchTagsImported) {
    const VENDOR_ID = 0x2047;
    const PRODUCT_ID = 0x0301;

    // WebHID 支援度與連線狀態
    const isHidSupported = ref(typeof navigator !== 'undefined' && 'hid' in navigator);
    const isConnected = ref(false);
    const deviceName = ref('');
    const storedTagCount = ref(null); // 槍內暫存筆數
    const isExporting = ref(false);
    const isClearing = ref(false);
    const autoClearAfterImport = ref(true); // 匯入成功後自動清空槍內暫存
    const statusText = ref('');
    const statusType = ref('idle'); // 'idle' | 'info' | 'success' | 'warning' | 'error'
    const lastExportStats = ref(null); // { uniqueCount, elapsedMs, pages }
    const showFlagGuideModal = ref(false); // 當 http:// 未設定 chrome://flags 時彈出引導視窗

    let hidDevice = null;
    let outputReportId = 0;
    let outputReportLen = 64;
    let rxStreamBuffer = [];

    // 批次導出狀態機
    let exportStartTime = 0;
    let exportedPages = 0;
    let exportedTagSet = new Set(); // 儲存所有解出的標籤字串 (同時支援 HEX 與 ASCII)
    let exportTimeoutTimer = null;

    // ---------------------------------------------------------
    // 1. 封包建構與 XOR 校驗碼
    // ---------------------------------------------------------
    const calcChecksum = (frameWithoutTail) => {
        let xor = 0;
        for (let i = 2; i < frameWithoutTail.length; i++) {
            xor ^= frameWithoutTail[i];
        }
        return xor & 0xFF;
    };

    const buildFrame = (cmd, payloadBytes = []) => {
        const totalLen = 2 + 2 + 1 + payloadBytes.length + 1 + 2;
        const buf = new Uint8Array(totalLen);
        buf[0] = 0xA5;
        buf[1] = 0x5A;
        buf[2] = (totalLen >> 8) & 0xFF;
        buf[3] = totalLen & 0xFF;
        buf[4] = cmd & 0xFF;
        for (let i = 0; i < payloadBytes.length; i++) {
            buf[5 + i] = payloadBytes[i] & 0xFF;
        }
        buf[totalLen - 3] = calcChecksum(buf.subarray(0, totalLen - 3));
        buf[totalLen - 2] = 0x0D;
        buf[totalLen - 1] = 0x0A;
        return buf;
    };

    const bytesToHex = (bytes) => {
        return Array.from(bytes)
            .map(b => b.toString(16).padStart(2, '0').toUpperCase())
            .join('');
    };

    const bytesToPrintableAscii = (bytes) => {
        let end = bytes.length;
        while (end > 0 && bytes[end - 1] === 0x00) end--;
        if (end === 0) return null;
        for (let i = 0; i < end; i++) {
            if (bytes[i] < 32 || bytes[i] > 126) return null;
        }
        return Array.from(bytes.subarray(0, end))
            .map(b => String.fromCharCode(b))
            .join('')
            .trim()
            .toUpperCase();
    };

    // ---------------------------------------------------------
    // 2. 底層 WebHID 發送與接收
    // ---------------------------------------------------------
    const sendCommandBytes = async (rawBytes) => {
        if (!hidDevice || !hidDevice.opened) {
            statusText.value = '請先連接 USB 掃描槍';
            statusType.value = 'warning';
            return false;
        }

        try {
            let offset = 0;
            while (offset < rawBytes.length) {
                const chunk = rawBytes.slice(offset, offset + outputReportLen);
                const padded = new Uint8Array(outputReportLen);
                padded.set(chunk, 0);
                await hidDevice.sendReport(outputReportId, padded);
                offset += outputReportLen;
            }
            return true;
        } catch (err) {
            console.error('[USB HID] 發送指令失敗:', err);
            statusText.value = `USB 通訊失敗: ${err.message}`;
            statusType.value = 'error';
            return false;
        }
    };

    const handleInputReport = (event) => {
        const { data } = event;
        const bytes = new Uint8Array(data.buffer, data.byteOffset, data.byteLength);

        // 每個以 A5 5A 開頭的 64B Report 視為新封包起點
        if (bytes[0] === 0xA5 && bytes[1] === 0x5A) {
            rxStreamBuffer = [];
        }
        for (let i = 0; i < bytes.length; i++) {
            rxStreamBuffer.push(bytes[i]);
        }

        processRxStreamBuffer();
    };

    const processRxStreamBuffer = () => {
        while (rxStreamBuffer.length >= 7) {
            const startIdx = rxStreamBuffer.findIndex((b, idx) => b === 0xA5 && rxStreamBuffer[idx + 1] === 0x5A);
            if (startIdx === -1) {
                rxStreamBuffer = [];
                return;
            }
            if (startIdx > 0) {
                rxStreamBuffer.splice(0, startIdx);
            }
            if (rxStreamBuffer.length < 7) return;

            const frameLen = (rxStreamBuffer[2] << 8) | rxStreamBuffer[3];
            if (frameLen < 7 || frameLen > 1024) {
                rxStreamBuffer.splice(0, 2);
                continue;
            }

            const cmd = rxStreamBuffer[4];

            // 0xEC / 0xE8: 離線暫存標籤批次封包
            if (cmd === 0xEC || cmd === 0xE8) {
                if (rxStreamBuffer.length < Math.min(frameLen, 64)) {
                    return;
                }
                parseBatchTagPacket(new Uint8Array(rxStreamBuffer));
                rxStreamBuffer = [];
                return;
            }

            if (rxStreamBuffer.length < frameLen) {
                return;
            }

            if (rxStreamBuffer[frameLen - 2] === 0x0D && rxStreamBuffer[frameLen - 1] === 0x0A) {
                const frame = new Uint8Array(rxStreamBuffer.splice(0, frameLen));
                dispatchFrame(frame);
                while (rxStreamBuffer.length > 0 && rxStreamBuffer[0] === 0x00) {
                    rxStreamBuffer.shift();
                }
            } else {
                rxStreamBuffer.splice(0, 2);
            }
        }
    };

    const dispatchFrame = (frame) => {
        const frameLen = (frame[2] << 8) | frame[3];
        const cmd = frame[4];
        const payload = frame.slice(5, frameLen - 3);

        // 0xEA: 槍內暫存筆數查詢 / 清空回應
        if (cmd === 0xEA) {
            if (payload.length >= 2) {
                const count = (payload[0] << 8) | payload[1];
                storedTagCount.value = count;
                if (isClearing.value && count === 0) {
                    isClearing.value = false;
                    statusText.value = '已成功清空掃描槍暫存！';
                    statusType.value = 'success';
                } else if (!isExporting.value) {
                    statusText.value = count > 0
                        ? `偵測到槍內有 ${count} 筆離線標籤，可點擊一鍵匯入`
                        : '目前掃描槍內無暫存標籤 (0 筆)';
                    statusType.value = count > 0 ? 'info' : 'idle';
                }
            } else if (payload.length === 1 && payload[0] === 0x00) {
                storedTagCount.value = 0;
                isClearing.value = false;
                statusText.value = '已成功清空掃描槍暫存！';
                statusType.value = 'success';
            }
        }
    };

    // ---------------------------------------------------------
    // 3. 解析 0xEC 批次標籤封包 (100% 對齊 UHFAPI.dll 狀態機)
    // ---------------------------------------------------------
    const parseBatchTagPacket = (data) => {
        let state = 0;
        let frameLen = 0;
        let tagCountInPacket = 0;
        let currentEpcLen = 0;
        let currentEpcBytes = null;
        let epcWriteIdx = 0;
        let packetTags = [];
        let frameComplete = false;

        for (let i = 0; i < data.length; i++) {
            const b = data[i];
            switch (state) {
                case 0:
                    if (b === 0xA5) {
                        state = 1;
                        packetTags = [];
                    }
                    break;
                case 1:
                    state = (b === 0x5A) ? 2 : 0;
                    break;
                case 2:
                    state = (b === 0x00) ? 3 : 0;
                    break;
                case 3:
                    frameLen = b;
                    if (frameLen <= 7) {
                        finishBatchExport();
                        return;
                    }
                    state = 4;
                    break;
                case 4:
                    state = (b === 0xEC || b === 0xE8 || b === 0xE1) ? 5 : 0;
                    break;
                case 5:
                    tagCountInPacket = b;
                    if (tagCountInPacket === 0) {
                        // tagCountInPacket === 0 代表所有分頁已拉取完畢！
                        finishBatchExport();
                        return;
                    }
                    state = 6;
                    break;
                case 6:
                    epcWriteIdx = 0;
                    currentEpcLen = b;
                    if (currentEpcLen <= 0 || currentEpcLen > 64) {
                        state = 0;
                        break;
                    }
                    currentEpcBytes = new Uint8Array(currentEpcLen);
                    state = 7;
                    break;
                case 7:
                    currentEpcBytes[epcWriteIdx++] = b;
                    if (epcWriteIdx === currentEpcLen) {
                        packetTags.push(currentEpcBytes);
                        state = (packetTags.length === tagCountInPacket) ? 8 : 6;
                    }
                    break;
                case 8:
                    state = 9;
                    break;
                case 9:
                    state = (b === 0x0D) ? 10 : 0;
                    break;
                case 10:
                    if (b === 0x0A) frameComplete = true;
                    else state = 0;
                    break;
            }
            if (frameComplete) break;
        }

        if (packetTags.length > 0) {
            for (const epcBytes of packetTags) {
                const hexStr = bytesToHex(epcBytes);
                const asciiStr = bytesToPrintableAscii(epcBytes);
                // 同時將 HEX 與可列印 ASCII (例如 313230... -> 120201032425) 加入待比對集合
                // 系統後端/前端會自動比對屬於本工單的 rfid_tag，並自動過濾非本單訊號
                if (hexStr) exportedTagSet.add(hexStr);
                if (asciiStr && asciiStr !== hexStr) exportedTagSet.add(asciiStr);
            }

            if (isExporting.value) {
                // 繼續拉取下一頁
                resetExportTimeout();
                setTimeout(() => requestNextExportPage(), 10);
            }
        }
    };

    const resetExportTimeout = () => {
        if (exportTimeoutTimer) clearTimeout(exportTimeoutTimer);
        exportTimeoutTimer = setTimeout(() => {
            if (isExporting.value) {
                finishBatchExport();
            }
        }, 1200);
    };

    const requestNextExportPage = async () => {
        exportedPages++;
        const cmd = buildFrame(0xEB, []);
        await sendCommandBytes(cmd);
    };

    const finishBatchExport = async () => {
        if (exportTimeoutTimer) {
            clearTimeout(exportTimeoutTimer);
            exportTimeoutTimer = null;
        }
        if (!isExporting.value) return;

        isExporting.value = false;
        const elapsedMs = Math.round(performance.now() - exportStartTime);
        const allCandidateTags = Array.from(exportedTagSet);

        if (allCandidateTags.length === 0) {
            statusText.value = '掃描槍內沒有可匯出的離線標籤';
            statusType.value = 'warning';
            storedTagCount.value = 0;
            return;
        }

        // 計算實際讀取到的唯一實體標籤數 (以原始 EPC 筆數估算)
        const estimatedUniqueRaw = storedTagCount.value || Math.ceil(allCandidateTags.length / 2);
        lastExportStats.value = {
            uniqueCount: estimatedUniqueRaw,
            elapsedMs,
            pages: exportedPages,
        };

        statusText.value = `⚡ USB 高速讀取完成 (${estimatedUniqueRaw} 筆標籤，耗時 ${elapsedMs}ms)，正在核對盤點單...`;
        statusType.value = 'success';

        // 交給外部 useInventoryScan 進行批次核對與上傳
        if (typeof onBatchTagsImported === 'function') {
            await onBatchTagsImported(allCandidateTags);
        }

        // 若勾選「匯入後自動清空掃描槍暫存」，自動發送 0xE9 FF
        if (autoClearAfterImport.value) {
            setTimeout(async () => {
                await clearScannerMemory(true);
            }, 250);
        }
    };

    // ---------------------------------------------------------
    // 4. 公開操作方法 (連線 / 查筆數 / 一鍵批次匯入 / 清空暫存)
    // ---------------------------------------------------------
    const attachDevice = async (device) => {
        try {
            if (!device.opened) {
                await device.open();
            }
            hidDevice = device;

            // 檢查 Output Report 長度
            for (const col of device.collections || []) {
                if (col.outputReports && col.outputReports.length > 0) {
                    const outRep = col.outputReports[0];
                    outputReportId = outRep.reportId || 0;
                    const items = outRep.items || [];
                    if (items.length > 0 && items[0].reportCount) {
                        outputReportLen = items[0].reportCount;
                    }
                }
            }

            device.oninputreport = handleInputReport;
            isConnected.value = true;
            deviceName.value = device.productName || 'UR-94E4 RFID Scanner';
            statusText.value = `已連線 ${deviceName.value}，正在讀取暫存筆數...`;
            statusType.value = 'info';

            // 連線成功後自動查詢槍內暫存筆數
            setTimeout(() => readTagCount(), 150);
            return true;
        } catch (err) {
            console.error('[USB HID] 開啟設備失敗:', err);
            statusText.value = `無法開啟掃描槍: ${err.message}`;
            statusType.value = 'error';
            return false;
        }
    };

    const connectScanner = async () => {
        isHidSupported.value = typeof navigator !== 'undefined' && 'hid' in navigator;
        if (!isHidSupported.value) {
            showFlagGuideModal.value = true;
            return false;
        }

        try {
            const devices = await navigator.hid.requestDevice({
                filters: [{ vendorId: VENDOR_ID, productId: PRODUCT_ID }],
            });

            if (!devices || devices.length === 0) {
                return false;
            }
            return await attachDevice(devices[0]);
        } catch (err) {
            console.error('[USB HID] 選擇設備失敗:', err);
            statusText.value = `連線取消或失敗: ${err.message}`;
            statusType.value = 'warning';
            return false;
        }
    };

    const disconnectScanner = async () => {
        if (hidDevice) {
            try {
                hidDevice.oninputreport = null;
                if (hidDevice.opened) await hidDevice.close();
            } catch (_) {}
            hidDevice = null;
        }
        isConnected.value = false;
        deviceName.value = '';
        storedTagCount.value = null;
        statusText.value = 'USB 掃描槍已中斷連線';
        statusType.value = 'idle';
    };

    const readTagCount = async () => {
        if (!isConnected.value) {
            const ok = await connectScanner();
            if (!ok) return;
        }
        const cmd = new Uint8Array([0xA5, 0x5A, 0x00, 0x09, 0xE9, 0x00, 0xE0, 0x0D, 0x0A]);
        await sendCommandBytes(cmd);
    };

    const importOfflineTags = async () => {
        if (!isConnected.value) {
            const ok = await connectScanner();
            if (!ok) return;
        }

        if (isExporting.value) return;

        // 先更新一次暫存筆數，接著立即啟動 0xEB 分頁匯出
        isExporting.value = true;
        exportedPages = 0;
        exportedTagSet = new Set();
        exportStartTime = performance.now();
        statusText.value = '正在從 USB 掃描槍高速讀取離線標籤...';
        statusType.value = 'info';

        resetExportTimeout();
        await requestNextExportPage();
    };

    const clearScannerMemory = async (silentConfirm = false) => {
        if (!isConnected.value) {
            const ok = await connectScanner();
            if (!ok) return;
        }
        if (!silentConfirm) {
            if (!confirm('確定要清空掃描槍內部暫存的所有離線標籤嗎？（槍身小螢幕筆數將歸零）')) {
                return;
            }
        }
        isClearing.value = true;
        const cmd = new Uint8Array([0xA5, 0x5A, 0x00, 0x09, 0xE9, 0xFF, 0x1F, 0x0D, 0x0A]);
        await sendCommandBytes(cmd);
    };

    // ---------------------------------------------------------
    // 5. 自動重連已授權的 USB 掃描槍 & 監聽插拔事件
    // ---------------------------------------------------------
    const handleHidConnect = async (e) => {
        if (e.device?.vendorId === VENDOR_ID && e.device?.productId === PRODUCT_ID) {
            await attachDevice(e.device);
        }
    };

    const handleHidDisconnect = (e) => {
        if (hidDevice && e.device === hidDevice) {
            hidDevice = null;
            isConnected.value = false;
            deviceName.value = '';
            storedTagCount.value = null;
            statusText.value = 'USB 掃描槍已拔除';
            statusType.value = 'idle';
        }
    };

    onMounted(async () => {
        isHidSupported.value = typeof navigator !== 'undefined' && 'hid' in navigator;
        if (!isHidSupported.value) return;

        navigator.hid.addEventListener('connect', handleHidConnect);
        navigator.hid.addEventListener('disconnect', handleHidDisconnect);

        // 若該電腦曾經授權過 UR-94E4，插上 USB 時自動無感連線並顯示暫存筆數！
        try {
            const grantedDevices = await navigator.hid.getDevices();
            const target = grantedDevices.find(d => d.vendorId === VENDOR_ID && d.productId === PRODUCT_ID);
            if (target) {
                await attachDevice(target);
            }
        } catch (_) {}
    });

    onUnmounted(() => {
        if (exportTimeoutTimer) clearTimeout(exportTimeoutTimer);
        if (typeof navigator !== 'undefined' && 'hid' in navigator) {
            navigator.hid.removeEventListener('connect', handleHidConnect);
            navigator.hid.removeEventListener('disconnect', handleHidDisconnect);
        }
        if (hidDevice && hidDevice.opened) {
            hidDevice.oninputreport = null;
            hidDevice.close().catch(() => {});
        }
    });

    return {
        isHidSupported,
        isConnected,
        deviceName,
        storedTagCount,
        isExporting,
        isClearing,
        autoClearAfterImport,
        statusText,
        statusType,
        lastExportStats,
        showFlagGuideModal,
        connectScanner,
        disconnectScanner,
        readTagCount,
        importOfflineTags,
        clearScannerMemory,
    };
}

