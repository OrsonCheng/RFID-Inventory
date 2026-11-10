<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProcessCalibrationData extends Command
{
    // 在終端機執行此 Worker 的指令名稱
    protected $signature = 'calibration:process';

    // 指令的簡短描述
    protected $description = '從 Redis 接收 Filebeat 傳送的校正資料並寫入 MySQL';

    // 將 Queue 名稱抽為類別屬性，方便集中管理
    protected string $mainQueue = 'calibration_session_queue';
    protected string $processingQueue = 'calibration_session_processing';
    protected string $failedQueue = 'calibration_session_failed';
    
    // 熔斷器狀態標記
    protected bool $dbIsDown = false;
	
	// ==========================================
    // 新增：心跳與計數屬性
    // ==========================================
    protected int $processedCount = 0;
    protected int $lastLogTime = 0;

    /**
     * 主程式進入點：負責流程編排 (Orchestration)
     */
    public function handle()
    {
        $this->info("Worker 已啟動。正在監聽 Redis 佇列: {$this->mainQueue}");

        // 1. 啟動時的孤兒資料救援 (Orphan Recovery)
        $this->recoverOrphanedData();
		
		// 初始化心跳時間為 Worker 啟動當下
        $this->lastLogTime = time();

        // 2. 無窮迴圈處理任務
        while (true) {
			// ==========================================
            // 【時間心跳機制】每 1hr 紀錄一次
            // ==========================================
            if ((time() - $this->lastLogTime) >= 3600) {
                Log::info("Calibration Worker 心跳正常。目前已累計處理 {$this->processedCount} 筆資料。");
                
                // 終端機畫面只有在加上 -v 參數時才印出，保持乾淨
                if ($this->getOutput()->isVerbose()) {
                    $this->info("[" . now()->toDateTimeString() . "] Worker 心跳正常，已累計處理 {$this->processedCount} 筆。");
                }
                
                $this->lastLogTime = time(); // 重置計時器
				$this->processedCount = 0;//reset counter
            }
			
            // 如果觸發了熔斷，進入探測與休眠模式
            if ($this->dbIsDown) {
                $this->waitForDatabaseRecovery();
                continue;
            }

            // 嘗試取件 (拿到原汁原味、未修補的 raw 字串)
            $rawPayload = $this->fetchNextJob();
            if (!$rawPayload) {
                continue; // Timeout 或沒資料，繼續等待下一圈
            }

            // 處理與分流
            try {
                // 執行核心解析與資料庫寫入
                $this->processJob($rawPayload);
                
                // 成功後從 Processing Queue 移除
                $this->acknowledgeJob($rawPayload);

            } catch (\PDOException | \Illuminate\Database\QueryException $e) {
                // 區分是「真的斷線」還是「SQL 語法/資料衝突錯誤」
                if ($this->isConnectionError($e)) {
                    $this->handleSystemError($e, $rawPayload);
                } else {
                    // 這是 SQL 資料錯誤 (例如 Primary Key 重複、欄位不可為 Null)
                    $this->handleDataError($e, $rawPayload);
                }
            } catch (\Exception $e) {
                // 處理 JSON 格式錯誤或缺少必要欄位等永久性錯誤
                $this->handleDataError($e, $rawPayload);
            }
        }
    }

    /**
     * 啟動救援：將上次當機殘留在 Processing Queue 的資料推回 Main Queue
     */
    private function recoverOrphanedData(): void
    {
        $orphanedCount = Redis::llen($this->processingQueue);
        if ($orphanedCount === 0) {
            return;
        }

        $this->warn("發現 {$orphanedCount} 筆殘留資料，正在推回 Main Queue...");
        while ($orphanedPayload = Redis::rpop($this->processingQueue)) {
            Redis::lpush($this->mainQueue, $orphanedPayload);
        }
        $this->info("孤兒資料已全數推回主佇列。");
    }

    /**
     * 熔斷探測：嘗試連線 DB，失敗則深度休眠
     */
    private function waitForDatabaseRecovery(): void
    {
        try {
            DB::connection()->getPdo();
            $this->dbIsDown = false;
            $this->info("資料庫連線已恢復！準備恢復 Redis 佇列處理...");
            // 保險起見，恢復連線後稍微緩衝 2 秒再開始拉資料
            sleep(2);
        } catch (\Exception $e) {
            $this->warn("MySQL 尚未啟動，Worker 進入深度休眠 60 秒...");
            sleep(60);
        }
    }

    /**
     * 取件：從 Redis 拿資料。
     * 【重要】必須保持原汁原味回傳，絕不能修改字串，否則後續 LREM 會因為字串不匹配而刪不掉資料。
     */
    private function fetchNextJob(): ?string
    {
        return Redis::command('BRPOPLPUSH', [$this->mainQueue, $this->processingQueue, 5]);
    }

    /**
     * 核心業務邏輯：修補 JSON、解析時間格式，並寫入資料庫
     */
    private function processJob(string $rawPayload): void
    {
        // 1. 建立一個專門用來解析的變數，不污染用來操作 Redis 的 $rawPayload
        $formattedPayload = $rawPayload;

        // 修補 JSON 結尾 (應對 Filebeat 傳輸異常截斷的 Bug)
        if (substr($formattedPayload, -1) !== '}') {
            if (substr($formattedPayload, -1) === ']') {
                $formattedPayload .= '}';
            } else {
                $formattedPayload .= '}'; 
            }
        }

        // 2. 解析 JSON
        $sessionData = json_decode($formattedPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($sessionData['RecordId'])) {
            throw new \Exception("JSON 解析失敗或缺少 RecordId");
        }

        // 3. 處理特殊的時間格式 (NextCalibrationDate)
        $nextDateStr = $sessionData['NextCalibrationDate'] ?? null;
        $formattedNextDate = null;

        if ($nextDateStr) {
            // 使用正則表達式抓取：日期、上下午、時間
            if (preg_match('/(\d{4}\/\d{1,2}\/\d{1,2})\s*(上午|下午)\s*(\d{1,2}:\d{2}:\d{2})/', $nextDateStr, $matches)) {
                $datePart = $matches[1];
                $ampm = ($matches[2] === '下午') ? 'PM' : 'AM';
                $timePart = $matches[3];

                // 重組為 PHP 認得的標準英文格式
                $englishDateStr = "$datePart $timePart $ampm";

                try {
                    $formattedNextDate = Carbon::parse($englishDateStr)->format('ymd');
                } catch (\Exception $e) {
                    Log::warning("無法解析重組後的時間: " . $englishDateStr);
                }
            } else {
                // 備用方案：直接讓 Carbon 嘗試解析
                try {
                    $formattedNextDate = Carbon::parse($nextDateStr)->format('ymd');
                } catch (\Exception $e) {
                    Log::warning("無法解析 NextCalibrationDate: " . $nextDateStr);
                }
            }
        }

        // 4. 資料庫交易 Transaction
        DB::transaction(function () use ($sessionData, $formattedNextDate) {
            
            // [A] 寫入 Master 表
            DB::table('calibration_masters')->insert([
                'id' => $sessionData['RecordId'],
                'Asset_Number' => $sessionData['AssetNumber'] ?? null,
                'Employee_Id' => $sessionData['EmployeeId'] ?? null,
                'Channel_Id' => $sessionData['ChannelId'] ?? null,
                'Channel_Ip' => $sessionData['ChannelIp'] ?? null,
                'Start_Time' => Carbon::parse($sessionData['StartTime'])->format('Y-m-d H:i:s'),
                'End_Time' => Carbon::parse($sessionData['EndTime'])->format('Y-m-d H:i:s'),
                'Next_Calibration_Date' => $formattedNextDate ?? null,
                'Calibration_Csv_Md5' => $sessionData['CalibrationCsvMd5'] ?? null,
                'Final_Result' => $sessionData['FinalStatus'] ?? null,
                'Temperature' => (isset($sessionData['Temperature']) && $sessionData['Temperature'] !== '') ? $sessionData['Temperature'] : null,
                'Humidity' => (isset($sessionData['Humidity']) && $sessionData['Humidity'] !== '') ? $sessionData['Humidity'] : null,
            ]);

            // [B1] 準備 Detail 陣列 (VerifyData)
            $detailRecords = [];
            if (!empty($sessionData['VerifyData'])) {
                foreach ($sessionData['VerifyData'] as $point) {
                    $detailRecords[] = $this->mapPoint($sessionData['RecordId'], $point);
                }
            }

            // [B2] 準備 PreVerifyDetail 陣列 (PreVerifyData)
            $preVerifydetailRecords = [];
            if (!empty($sessionData['PreVerifyData'])) {
                foreach ($sessionData['PreVerifyData'] as $point) {
                    $preVerifydetailRecords[] = $this->mapPoint($sessionData['RecordId'], $point);
                }
            }

            // [B3] 準備 CalibrationRawLog 陣列 (CalibrationData)
            $calibrationRawLog = [];
            if (!empty($sessionData['CalibrationData'])) {
                foreach ($sessionData['CalibrationData'] as $point) {
                    $calibrationRawLog[] = $this->mapPoint($sessionData['RecordId'], $point);
                }
            }

            // [C] 批次寫入各子表
            if (count($detailRecords) > 0) {
                DB::table('calibration_details')->insert($detailRecords);
            }
            if (count($preVerifydetailRecords) > 0) {
                DB::table('pre_verify_details')->insert($preVerifydetailRecords);
            }
            if (count($calibrationRawLog) > 0) {
                DB::table('calibration_raw_log')->insert($calibrationRawLog);
            }
        });

        $this->info("RecordId: {$sessionData['RecordId']} 處理成功！");
    }

    /**
     * 任務成功：從 Processing Queue 中移除原始資料
     */
    private function acknowledgeJob(string $rawPayload): void
    {
        Redis::lrem($this->processingQueue, 1, $rawPayload);
		// 成功處理一筆，計數器 +1
        $this->processedCount++;
    }

    /**
     * 處理暫時性系統錯誤 (觸發熔斷)
     */
    private function handleSystemError(\Exception $e, string $rawPayload): void
    {
        Log::error("資料庫連線異常，觸發熔斷機制: " . $e->getMessage());
        $this->error("偵測到資料庫斷線，將資料退回，準備進入休眠模式...");

        // 退回 Main Queue 等待 DB 恢復後重試，並從 Processing Queue 清除
        Redis::lpush($this->mainQueue, $rawPayload);
        Redis::lrem($this->processingQueue, 1, $rawPayload);

        // 開啟熔斷標記，下一圈迴圈就會進入探測模式
        $this->dbIsDown = true;
        sleep(5);
    }

    /**
     * 處理永久性資料錯誤 (移入死信佇列 DLQ)
     */
    private function handleDataError(\Exception $e, string $rawPayload): void
    {
        Log::error("資料格式無法解析或寫入失敗，移入 Failed Queue: " . $e->getMessage());
        $this->error("發生資料錯誤: " . $e->getMessage());
        
        $failedData = json_encode([
            'error_message' => $e->getMessage(),
            'failed_at' => now()->toDateTimeString(),
            'original_payload' => $rawPayload
        ], JSON_UNESCAPED_UNICODE);
        
        // 移入 Failed Queue 並從 Processing Queue 移除
        Redis::lpush($this->failedQueue, $failedData);
        Redis::lrem($this->processingQueue, 1, $rawPayload);
    }

    /**
     * 將 JSON 中的單筆測試資料 Mapping 為資料庫欄位格式
     */
    private function mapPoint($recordId, $point): array
    {
        return [
            'Record_Id' => $recordId,
            'Calibration_Type' => $point['CalibrationType'] ?? null,
            'Range_Info' => $point['RangeInfo'] ?? null,
            'Test_Item' => $point['TestItem'] ?? null,
            'Standard_Value' => $point['StandardValue'] ?? 0,
            'Meter_Value' => $point['MeterValue'] ?? null, // 處理可能是 null 的情況
            'ADC_Value' => $point['ADCValue'] ?? 0,
            'Difference' => $point['Difference'] ?? 0,
            'Status' => $point['Status'] ?? null,
        ];
    }
	
	/**
     * 判斷是否為真實的資料庫連線中斷錯誤
     */
    private function isConnectionError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        // 常見的 MySQL/伺服器斷線關鍵字與 SQLSTATE 碼
        return Str::contains($message, [
            'server has gone away',
            'no connection to the server',
            'lost connection',
            'is dead or not enabled',
            'connection refused',
            'actively refused', // 對應 [2002]
            'connection attempt failed',
            'has failed to respond',
            'read error on connection',
            'sqlstate[hy000] [2002]',
            'sqlstate[08s01]',
        ]);
    }
}