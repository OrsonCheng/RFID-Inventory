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
        $sequenceCounter = 0; 
         // 查詢目前 DB 中最大的流水號前綴 (前5碼 Hex)
        // 使用 SUBSTR(rfid_tag, 1, 5) 截取前 5 位，並找最大值
        $maxSeqHex = FixedAsset::whereNotNull('rfid_tag')
            ->where('rfid_tag', '!=', '')
            ->selectRaw('MAX(SUBSTR(rfid_tag, 1, 5)) as max_seq')
            ->value('max_seq');

        if ($maxSeqHex) {
            // 將找到的 16 進位 (例如 "0001A") 轉為 10 進位 (26)，然後 +1 變成 27
            $currentMaxDec = hexdec($maxSeqHex);
            $sequenceCounter = $currentMaxDec + 1;

            $this->info("偵測到既有 RFID 資料，目前最大流水號: {$maxSeqHex} (Dec: {$currentMaxDec})");
            $this->info("本次將從流水號: " . strtoupper(dechex($sequenceCounter)) . " (Dec: {$sequenceCounter}) 開始接續生成。");
        } else {
            $this->info("未偵測到既有 RFID 資料，流水號將從 0 開始。");
        }

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
        
        if(str_starts_with($tag, 'A') || str_starts_with($tag, 'C')){
            $prefix = substr($tag, 0, 1);
        }elseif(str_contains($tag, 'H-')) {
            $prefix = 'A';
        } elseif (str_contains($tag, 'NC-')) {
            $prefix = 'C';
        } else {
            // 如果不符合 H 或 NC 規則，這裡需要決定怎麼辦
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