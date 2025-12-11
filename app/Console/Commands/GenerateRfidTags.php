<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FixedAsset;
use Illuminate\Support\Str;

class GenerateRfidTags extends Command
{
    /**
     * 終端機執行的指令名稱
     */
    protected $signature = 'asset:generate-rfid {--force : 強制重新生成所有資產的 RFID (覆蓋既有)}';

    /**
     * 指令描述
     */
    protected $description = '根據規則自動生成並寫入 RFID 編號';

    public function handle()
    {
        $this->info('開始生成 RFID 編號...');

        // 1. 獲取需要處理的資產
        // 預設只處理 rfid_tag 為空的，除非加了 --force 參數
        $query = FixedAsset::query();
        
        if (!$this->option('force')) {
            $query->whereNull('rfid_tag')->orWhere('rfid_tag', '');
        }

        // 為了依照「同一類設備連續編號」，我們依照 brand + model 排序
        // 排序優先級: Brand -> Model -> ID
        $assets = $query->orderBy('brand')
                        ->orderBy('model')
                        ->orderBy('id')
                        ->get();

        if ($assets->isEmpty()) {
            $this->info('沒有需要處理的資產。');
            return;
        }

        // 全局流水號計數器 (Global Sequence Counter)
        // 為了延續之前的編號，我們需要知道目前資料庫中已經用到哪個流水號了
        // 如果是全新生成，從 0 開始。如果是增量生成，應該從最大值+1開始？
        // 根據你的描述，這裡假設是「本次執行的這批資料」內部連續。
        // 如果你需要「跨越多次執行」都連續，那需要去資料庫查最大的 RFID 流水號。
        // 這裡我們先實作：本次執行從 0 開始 (或你可以指定起始值)
        
        // [修正] 為了更穩健，我們應該去查資料庫目前已有的最大流水號，避免重複
        // 但你的需求可能是「這批資料重頭編」，我們暫時設為 0
        $sequenceCounter = 0; 

        // 顯示總數
        $this->info("共找到 " . $assets->count() . " 筆資產，準備生成...");

        $totalUpdated = 0;

        // 因為流水號是全域連續的，我們不需要特別分組迴圈，直接跑這個排序好的列表即可
        // 但為了 Log 好看，我們可以記錄當前處理的 brand/model
        $currentGroupKey = '';

        foreach ($assets as $asset) {
            try {
                // 判斷是否換了一類設備 (僅為了 Log 顯示)
                $groupKey = ($asset->brand ?? 'Unknown') . ' - ' . ($asset->model ?? 'Unknown');
                if ($groupKey !== $currentGroupKey) {
                    $this->line("------------------------------------------------");
                    $this->info("切換至類別: {$groupKey}");
                    $currentGroupKey = $groupKey;
                }

                // === 生成規則 ===

                // Part A: 流水號 (5位 16進制, 0~F)
                // dechex 轉 16 進位, strtoupper 轉大寫, str_pad 補 0
                $hexSeq = strtoupper(dechex($sequenceCounter));
                // 檢查是否超過 5 位 (Hex FFFFF = 1048575)，超過會溢出
                if (strlen($hexSeq) > 5) {
                    $this->error("  [Error] 流水號溢出 (超過 FFFFF)，請檢查資料量或重置計數器。ID: {$asset->id}");
                    break; // 停止執行，避免生成錯誤資料
                }
                $partA_Seq = str_pad($hexSeq, 5, '0', STR_PAD_LEFT);

                // Part B: 資產編號 (11位)
                // 規則: -H- => A, -NC- => C, 去掉其他符號
                $partB_Asset = $this->parseAssetTag($asset->asset_tag);
                if (!$partB_Asset) {
                    $this->warn("  [Skip] 資產編號格式無法解析: {$asset->asset_tag} (ID: {$asset->id})");
                    continue;
                }

                // Part C: PPID (8位數字)
                // 規則: 去除字母符號, 取最後8位, 不足補0
                $partC_Ppid = $this->parsePpid($asset->ppid);

                // 組合: A + B + C = 24位
                $rfidTag = $partA_Seq . $partB_Asset . $partC_Ppid;

                // 檢查長度是否正確 (防呆)
                if (strlen($rfidTag) !== 24) {
                    $this->error("  [Error] 生成長度錯誤 ({$rfidTag}), 跳過 ID: {$asset->id}");
                    continue;
                }

                // === 寫入資料庫 ===
                $asset->rfid_tag = $rfidTag;
                $asset->save();

                // 顯示進度
                // 顯示 Hex 流水號，方便確認
                $this->line("  [OK] ID:{$asset->id} | Seq:{$partA_Seq} | {$rfidTag}");
                
                // 計數器 +1 (全域累加，不重置)
                $sequenceCounter++;
                $totalUpdated++;

            } catch (\Exception $e) {
                $this->error("  [Exception] ID: {$asset->id} - " . $e->getMessage());
            }
        }

        $this->info("處理完成！共更新 {$totalUpdated} 筆資料。");
    }

    /**
     * 解析資產編號
     * 規則: -H- 轉 A, -NC- 轉 C, 剩餘只留數字
     */
    private function parseAssetTag($tag)
    {
        if (empty($tag)) return null;

        $prefix = '';
        
        // 判斷前綴
        if (str_contains($tag, 'H-')) {
            $prefix = 'A';
        } elseif (str_contains($tag, 'NC-')) {
            $prefix = 'C';
        } else {
            // 如果不符合 H 或 NC 規則，這裡需要決定怎麼辦
            // 暫時邏輯：如果沒有特定前綴，預設用 'X' 或保留原樣？
            // 根據你的描述，假設所有資料都符合 H/NC 規則
            // 這裡做一個容錯：直接取所有數字，前面補 A (或是報錯)
            // 為了安全，我們先回傳 null 讓外部跳過報錯
            // return null; 
            
            // 或者：直接硬轉，把所有非數字去掉
             $prefix = 'E'; // Unknown
        }

        // 取出數字部分
        $numbers = preg_replace('/[^0-9]/', '', $tag);

        // 組合 (需求是 11 位: 1位字母 + 10位數字)
        // 確保數字部分取最後 10 位 (如果超過) 或補 0 (如果不足)
        // 通常資產編號數字部分是固定的，這裡直接截取
        
        // 如果數字超過 10 位，取後面 10 位；不足補 0
        // 例子: 2015001435 (10位) -> 完美
        $cleanNumbers = substr($numbers, -10);
        $cleanNumbers = str_pad($cleanNumbers, 10, '0', STR_PAD_LEFT);

        return $prefix . $cleanNumbers;
    }

    /**
     * 解析 PPID
     * 規則: 去除字母符號，取最後 8 位數字，不足補 0
     */
    private function parsePpid($ppid)
    {
        // 如果 PPID 是空的，就全是 0
        if (empty($ppid)) {
            return '00000000';
        }

        // 1. 去除所有字母和符號 (只留數字)
        $onlyNumbers = preg_replace('/[^0-9]/', '', $ppid);

        // 2. 取最後 8 位
        $last8 = substr($onlyNumbers, -8);

        // 3. 不足 8 位前面補 0
        return str_pad($last8, 8, '0', STR_PAD_LEFT);
    }
}