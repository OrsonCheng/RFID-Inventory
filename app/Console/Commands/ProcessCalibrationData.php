<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessCalibrationData extends Command
{
    // 在終端機執行此 Worker 的指令名稱
    protected $signature = 'calibration:process';

    // 指令的簡短描述
    protected $description = '從 Redis 接收 Filebeat 傳送的校正資料並寫入 MySQL';

    public function handle()
    {
        // 您的 Redis Queue 名稱
        $mainQueue = 'calibration_session_queue';
        $processingQueue = 'calibration_session_processing';

        $this->info("Worker 已啟動。正在監聽 Redis 佇列: {$mainQueue}");

        // 無窮迴圈，讓 Worker 持續常駐執行
        while (true) {
            try {
                // 1. 【高可靠取件】：BRPOPLPUSH (阻塞式右出左入)
                // 這裡設定 timeout 為 5 秒。如果 Main Queue 沒資料，會等待 5 秒再繼續下一圈
                // 這能大幅減少 Redis 的壓力與 CPU 空轉
                $rawPayload = Redis::command('BRPOPLPUSH', [$mainQueue, $processingQueue, 5]);

                // 如果 5 秒內沒拿到資料，繼續下一圈
                if (!$rawPayload) {
                    continue; 
                }

                $this->info("收到新資料，開始處理...");

                // 2. 解析 Filebeat 外層包裝
                $filebeatLog = json_decode($rawPayload, true);
                if (!isset($filebeatLog['message'])) {
                    throw new \Exception("Filebeat 格式錯誤或缺少 'message' 欄位");
                }

                // 3. 解析實際的 Calibration JSON 內容
                $sessionData = json_decode($filebeatLog['message'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !isset($sessionData['RecordId'])) {
                    throw new \Exception("JSON 解析失敗或缺少 RecordId");
                }

                // 4. 【資料庫交易 Transaction】
                // Laravel 會自動處理 Commit，如果閉包內發生 Exception 則自動 Rollback
                DB::transaction(function () use ($sessionData) {
                    
                    // [A] 寫入 Master 表 (利用 insertOrIgnore 實現冪等性，避免重複寫入報錯)
                    DB::table('CalibrationSession')->insertOrIgnore([
                        'RecordId' => $sessionData['RecordId'],
                        'AssetNumber' => $sessionData['AssetNumber'] ?? null,
                        'EmployeeId' => $sessionData['EmployeeId'] ?? null,
                        'ChannelId' => $sessionData['ChannelId'] ?? null,
                        'ChannelIp' => $sessionData['ChannelIp'] ?? null,
                        // 利用 Carbon 轉換 ISO8601 時間為 MySQL 格式 (Y-m-d H:i:s)
                        'StartTime' => Carbon::parse($sessionData['StartTime'])->format('Y-m-d H:i:s'),
                        'EndTime' => Carbon::parse($sessionData['EndTime'])->format('Y-m-d H:i:s'),
                        'NextCalibrationDate' => $sessionData['NextCalibrationDate'] ?? null,
                        'CalibrationCsvMd5' => $sessionData['CalibrationCsvMd5'] ?? null,
                        'FinalStatus' => $sessionData['FinalStatus'] ?? null,
                    ]);

                    // [B] 準備 Detail 陣列 (將 CalibrationData 與 PreVerifyData 合併處理)
                    $detailRecords = [];

                    if (!empty($sessionData['CalibrationData'])) {
                        foreach ($sessionData['CalibrationData'] as $point) {
                            $detailRecords[] = $this->mapPoint($sessionData['RecordId'], $point);
                        }
                    }

                    if (!empty($sessionData['PreVerifyData'])) {
                        foreach ($sessionData['PreVerifyData'] as $point) {
                            $detailRecords[] = $this->mapPoint($sessionData['RecordId'], $point);
                        }
                    }

                    // [C] 批次寫入 Detail 表 (Batch Insert，效能極佳)
                    if (count($detailRecords) > 0) {
                        DB::table('CalibrationData')->insert($detailRecords);
                    }
                });

                // 5. 【ACK 確認】：資料庫寫入成功後，將資料從 Processing Queue 刪除
                Redis::lrem($processingQueue, 1, $rawPayload);
                
                $this->info("RecordId: {$sessionData['RecordId']} 已成功寫入資料庫！");

            } catch (\Exception $e) {
                // 發生錯誤時記錄到 storage/logs/laravel.log
                Log::error("處理校正資料發生錯誤: " . $e->getMessage());
                $this->error("發生錯誤: " . $e->getMessage());
                
                // 休息 3 秒避免錯誤時瘋狂洗 Log
                sleep(3); 
            }
        }
    }

    /**
     * 將 JSON 中的單筆測試資料 Mapping 為資料庫欄位格式
     */
    private function mapPoint($recordId, $point)
    {
        return [
            'RecordId' => $recordId,
            'CalibrationType' => $point['CalibrationType'] ?? null,
            'RangeInfo' => $point['RangeInfo'] ?? null,
            'TestItem' => $point['TestItem'] ?? null,
            'StandardValue' => $point['StandardValue'] ?? 0,
            // 處理可能是 null 的 MeterValue
            'MeterValue' => $point['MeterValue'], 
            'ADCValue' => $point['ADCValue'] ?? 0,
            'Difference' => $point['Difference'] ?? 0,
            'Status' => $point['Status'] ?? null,
        ];
    }
}