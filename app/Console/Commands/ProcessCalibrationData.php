<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\CalibrationMaster;
use App\Models\PreVerifyDetail;
use App\Models\CalibrationDetail;

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

        // 測試：直接列出目前 Laravel 看到的 Key 數量
        $count = Redis::llen($mainQueue);
        $this->info("目前隊列中的資料筆數: " . $count);

        // 無窮迴圈，讓 Worker 持續常駐執行
        while (true) {
            try {
                // 1. 【高可靠取件】：BRPOPLPUSH (阻塞式右出左入)
                // 這裡設定 timeout 為 5 秒。如果 Main Queue 沒資料，會等待 5 秒再繼續下一圈
                // 這能大幅減少 Redis 的壓力與 CPU 空轉
                $rawPayload = Redis::command('BRPOPLPUSH', [$mainQueue, $processingQueue, 5]);
                $formattedPayload = $rawPayload;

                // 如果 5 秒內沒拿到資料，繼續下一圈
                if (!$rawPayload) {
                    $this->info("No Data...");
                    continue;
                }

                $this->info("收到新資料，開始處理...");

                // 如果最後一個字元不是 }，嘗試補齊
                if (substr($formattedPayload, -1) !== '}') {
                    // 檢查是否是以 ] 結尾（代表陣列結束但物件沒結束）
                    if (substr($formattedPayload, -1) === ']') {
                        $formattedPayload .= '}';
                    }
                }


                // 3. 解析實際的 Calibration JSON 內容
                $sessionData = json_decode($formattedPayload, true);


                if (json_last_error() !== JSON_ERROR_NONE || !isset($sessionData['RecordId'])) {
                    throw new \Exception("JSON 解析失敗或缺少 RecordId");
                }

                $nextDateStr = $sessionData['NextCalibrationDate'] ?? null;
                $formattedNextDate = null;

                if ($nextDateStr) {
                    // 使用正則表達式抓取：日期、上下午、時間
                    // 支援匹配 "2027/3/30 下午 02:32:42" 或 "2027/03/30 上午 2:32:42"
                    if (preg_match('/(\d{4}\/\d{1,2}\/\d{1,2})\s*(上午|下午)\s*(\d{1,2}:\d{2}:\d{2})/', $nextDateStr, $matches)) {

                        $datePart = $matches[1]; // 2027/3/30
                        $ampm = ($matches[2] === '下午') ? 'PM' : 'AM';
                        $timePart = $matches[3]; // 02:32:42

                        // 重組為 PHP 認得的標準英文格式："2027/3/30 02:32:42 PM"
                        $englishDateStr = "$datePart $timePart $ampm";

                        try {
                            $formattedNextDate = \Carbon\Carbon::parse($englishDateStr)->format('ymd');
                        } catch (\Exception $e) {
                            Log::warning("無法解析重組後的時間: " . $englishDateStr);
                        }
                    } else {
                        // 萬一機台給的是標準格式或是其他無法用正則匹配的格式（備用方案）
                        try {
                            $formattedNextDate = \Carbon\Carbon::parse($nextDateStr)->format('ymd');
                        } catch (\Exception $e) {
                            Log::warning("無法解析 NextCalibrationDate: " . $nextDateStr);
                        }
                    }
                }

                // 4. 【資料庫交易 Transaction】
                // Laravel 會自動處理 Commit，如果閉包內發生 Exception 則自動 Rollback
                DB::transaction(function () use ($sessionData, $formattedNextDate) {

                    $this->info(" formattedNextDate==>  {$formattedNextDate}");
                    // [A] 寫入 Master 表 (利用 insertOrIgnore 實現冪等性，避免重複寫入報錯)
                    DB::table('calibration_masters')->insert([
                        'id' => $sessionData['RecordId'],
                        'Asset_Number' => $sessionData['AssetNumber'] ?? null,
                        'Employee_Id' => $sessionData['EmployeeId'] ?? null,
                        'Channel_Id' => $sessionData['ChannelId'] ?? null,
                        'Channel_Ip' => $sessionData['ChannelIp'] ?? null,
                        // 利用 Carbon 轉換 ISO8601 時間為 MySQL 格式 (Y-m-d H:i:s)
                        'Start_Time' => Carbon::parse($sessionData['StartTime'])->format('Y-m-d H:i:s'),
                        'End_Time' => Carbon::parse($sessionData['EndTime'])->format('Y-m-d H:i:s'),
                        //'Next_Calibration_Date' => $sessionData['NextCalibrationDate'] ?? null,
                        'Next_Calibration_Date' => $formattedNextDate ?? null,
                        'Calibration_Csv_Md5' => $sessionData['CalibrationCsvMd5'] ?? null,
                        'Final_Result' => $sessionData['FinalStatus'] ?? null,
                    ]);

                    // [B1] 準備 Detail 陣列 (VerifyData  校正後的驗證結果 用這個資料做校驗報告)
                    $detailRecords = [];

                    if (!empty($sessionData['VerifyData'])) {
                        foreach ($sessionData['VerifyData'] as $point) {
                            $detailRecords[] = $this->mapPoint($sessionData['RecordId'], $point);
                        }
                    }


                    // [B2] 準備 preVerifydetailRecords 陣列 (preVerifyData )
                    $preVerifydetailRecords = [];

                    if (!empty($sessionData['PreVerifyData'])) {
                        foreach ($sessionData['PreVerifyData'] as $point) {
                            $preVerifydetailRecords[] = $this->mapPoint($sessionData['RecordId'], $point);
                        }
                    }

                    // [B3] 準備 calibrationRawLog 陣列 (calibrationData  校正過程的數據 用這個資料調整ADC 與校驗報告無關)
                    $calibrationRawLog = [];

                    if (!empty($sessionData['CalibrationData'])) {
                        foreach ($sessionData['CalibrationData'] as $point) {
                            $calibrationRawLog[] = $this->mapPoint($sessionData['RecordId'], $point);
                        }
                    }

                    // [C] 批次寫入 Detail 表
                    if (count($detailRecords) > 0) {
                        DB::table('calibration_details')->insert($detailRecords);
                    }

                    // [C] 批次寫入 pre_verify_details 表
                    if (count($preVerifydetailRecords) > 0) {
                        DB::table('pre_verify_details')->insert($preVerifydetailRecords);
                    }

                    // [C] 批次寫入 calibration_raw_log 表
                    if (count($preVerifydetailRecords) > 0) {
                        DB::table('calibration_raw_log')->insert($calibrationRawLog);
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
            'Record_Id' => $recordId,
            'Calibration_Type' => $point['CalibrationType'] ?? null,
            'Range_Info' => $point['RangeInfo'] ?? null,
            'Test_Item' => $point['TestItem'] ?? null,
            'Standard_Value' => $point['StandardValue'] ?? 0,
            // 處理可能是 null 的 MeterValue
            'Meter_Value' => $point['MeterValue'],
            'ADC_Value' => $point['ADCValue'] ?? 0,
            'Difference' => $point['Difference'] ?? 0,
            'Status' => $point['Status'] ?? null,
        ];
    }
}