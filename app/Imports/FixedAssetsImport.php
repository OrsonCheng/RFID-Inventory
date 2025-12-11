<?php

namespace App\Imports;

use App\Models\FixedAsset;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FixedAssetsImport implements ToModel, WithStartRow, WithChunkReading, WithCalculatedFormulas
{
    public function startRow(): int
    {
        return 2; // 跳過標題列，從第 2 行開始
    }

    public function model(array $row)
    {
        // [索引對照表] 根據 TE-asset-import.xlsx
        // 0: #
        // 1: RFID編號 -> rfid_tag
        // 2: PPID -> ppid [新增]
        // 3: 資產編號 -> asset_tag (Key)
        // 4: 設備名稱 -> name
        // 5: 規格說明 -> spec [新增]
        // 6: 品牌 -> brand [新增]
        // 7: 型號 -> model
        // 8: 取得日期 -> purchased_at
        // 9: 來源類型 -> source_type [新增]
        // 10: 所屬公司 -> company [新增]
        // 11: 部門代碼 -> department_code [新增]
        // 12: 部門名稱 -> department_name (原本叫 department)
        // 13: 廠區 -> factory_area [新增]
        // 14: 樓層 -> floor [新增]
        // 15: 綫別／區域 -> area [新增]
        // 16: 站別／儲位 -> location
        // 17: 保管人 -> keeper
        // 18: 状态 -> status

        // 1. 必填檢查：沒有資產編號就跳過
        $assetTag = isset($row[3]) ? trim($row[3]) : null;
        if (empty($assetTag)) {
            return null;
        }

        // 2. 日期處理 (索引 8)
        $purchasedAt = null;
        if (isset($row[8])) {
            try {
                if (is_numeric($row[8])) {
                    $purchasedAt = Date::excelToDateTimeObject($row[8]);
                } else {
                    $purchasedAt = Carbon::parse($row[8]);
                }
            } catch (\Exception $e) {
                // 日期格式錯誤，留空即可
                $purchasedAt = null;
            }
        }

        // 3. RFID 處理 (空字串轉 null)
        $rfid = isset($row[1]) ? trim($row[1]) : null;
        if ($rfid === '') $rfid = null;

        try {
            // 4. 執行寫入或更新
            $asset = FixedAsset::updateOrCreate(
                ['asset_tag' => $assetTag], // 搜尋條件
                [
                    'rfid_tag'        => $rfid,
                    'ppid'            => $row[2] ?? null,  // [2] PPID
                    'name'            => $row[4] ?? '未命名資產', // [4] 設備名稱
                    'spec'            => $row[5] ?? null,  // [5] 規格說明
                    'brand'           => $row[6] ?? null,  // [6] 品牌
                    'model'           => $row[7] ?? null,  // [7] 型號
                    'purchased_at'    => $purchasedAt,     // [8] 取得日期
                    'source_type'     => $row[9] ?? null,  // [9] 來源類型
                    'company'         => $row[10] ?? null, // [10] 所屬公司
                    'department_code' => $row[11] ?? null, // [11] 部門代碼
                    'department_name' => $row[12] ?? null, // [12] 部門名稱
                    'factory_area'    => $row[13] ?? null, // [13] 廠區
                    'floor'           => $row[14] ?? null, // [14] 樓層
                    'area'            => $row[15] ?? null, // [15] 綫別／區域
                    'location'        => $row[16] ?? null, // [16] 站別／儲位
                    'keeper'          => $row[17] ?? null, // [17] 保管人
                    'status'          => $row[18] ?? '使用中', // [18] 状态
                    
                    // 備註欄位：如果 Excel 沒有專門的備註欄，可以用規格說明或其他欄位填充，或者留空
                    // 這裡我暫時不填入 remark，因为 Excel 没有這一欄，spec 已經存到 spec 欄位了
                    // 'remark' => ... 
                ]
            );
            
            return $asset;

        } catch (\Exception $e) {
            Log::error("匯入失敗 [{$assetTag}]: " . $e->getMessage());
            return null;
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}