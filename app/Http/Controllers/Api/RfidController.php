<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache; // 引入 Cache
use Illuminate\Validation\Rule;

class RfidController extends Controller
{
    public function lookup(Request $request)
    {
        $request->validate([
            'asset_code' => 'required|string',
        ]);

        $assetCode = $request->asset_code;
        
        // 定義 Cache 的 Key，建議加上前綴避免衝突
        // 例如: rfid_lookup:A001
        $cacheKey = "rfid_lookup:{$assetCode}";
        
        // 設定緩存時間 (秒)，例如 1 天 (60 * 60 * 24)
        // 因為資產的基本資料不太常變動，設長一點沒關係
        $ttl = 86400; 

        // 核心邏輯：Cache::remember
        // 1. 先去 Redis 找 $cacheKey
        // 2. 有找到 -> 直接回傳 (不進 DB)
        // 3. 沒找到 -> 執行 function 內的 DB 查詢 -> 把結果寫入 Redis -> 回傳
        $result = Cache::remember($cacheKey, $ttl, function () use ($assetCode) {
            
            // 這裡只有在 Redis 沒資料時才會執行
            $asset = FixedAsset::where('asset_tag', $assetCode)->first();

            if (!$asset) {
                return null; // 標記為空，以便外層判斷
            }
			
            // 整理要回傳並緩存的純資料陣列
            // 建議只存需要的欄位，減少 Redis 記憶體消耗
            return [
                'asset_id' => $asset->id,
                'asset_tag' => $asset->asset_tag,
                'name'     => $asset->name,
                'rfid_tag' => $asset->rfid_tag, 
				'remark'    => $asset->remark,   // 回傳寫入的值確認
				'is_tagged' => !empty($asset->remark), // 如果 remark 有東西，就是 true
            ];
        });

        // 處理找不到資料的情況 (Redis 回傳 null 或 DB 查無資料)
        if (is_null($result)) {
            // 如果你希望「查不到」這個狀態也要被緩存 (防止 Cache Penetration)，
            // 可以在 remember 內部回傳一個特定的標記陣列，但通常 404 不需要太激進的緩存。
            return response()->json([
                'status' => 'error',
                'message' => '找不到此資產編號',
            ], 404);
        }

        // 回傳成功
        return response()->json([
            'status' => 'success',
            'data' => $result
        ], 200);
    }
	
	/**
     * C# 寫入 RFID 成功後呼叫此 API
     * 用途：將操作員輸入的自定義編號寫入 remark，代表已貼標
     */
    public function bind(Request $request)
    {
        // 1. 先把資產找出來 (為了取得 ID 進行後續的 unique 排除驗證)
        $asset = FixedAsset::where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json([
                'message' => '找不到此資產編號 (Asset Tag 不存在)',
                'errors' => ['asset_tag' => ['找不到此資產']]
            ], 404);
        }

        // 2. 進行驗證 (加入唯一性檢查)
        $request->validate([
            'asset_tag'     => 'required|string',
            'custom_number' => [
                'required', 
                'string',
                // ★ 核心邏輯：在 fixed_assets 表的 remark 欄位檢查唯一性
                // 但要「忽略 (ignore)」目前這台資產 ($asset->id)
                // 這樣如果是同一台資產重複操作，才不會自己擋自己
                Rule::unique('fixed_assets', 'remark')->ignore($asset->id),
            ],
        ], [
            // 自訂錯誤訊息 (讓 C# 端顯示出來比較好看)
            'custom_number.unique' => '錯誤：這個自定義編號已經被其他資產使用了！',
        ]);

        // 3. 更新資料
        $asset->remark = $request->custom_number;
        $asset->save(); // 觸發 Observer 清除 Cache

        // 4. 回傳成功
        return response()->json([
            'status' => 'success',
            'message' => '資產綁定成功',
            'data' => [
                'asset_tag' => $asset->asset_tag,
                'remark'    => $asset->remark,
                'is_tagged' => true,
            ]
        ], 200);
    }
	
	/**
     * 確認站 API：比對 RFID EPC 與 自定義編號 是否一致
     */
    public function verify(Request $request)
    {
        // 1. 驗證參數
        $request->validate([
            'rfid_epc'      => 'required|string',
            'custom_number' => 'required|string',
        ]);

        // 2. 核心邏輯：
        // 嘗試找出 "remark = 自定義編號" 且 "rfid_tag = 讀到的EPC" 的資產
        $asset = FixedAsset::where('remark', $request->custom_number)
                           ->where('rfid_tag', $request->rfid_epc) // 這裡視你的欄位名而定 (rfid_tag 或 rfid_epc)
                           ->first();

        // 3. 判斷結果
        if ($asset) {
            return response()->json([
                'status' => 'success',
                'message' => '驗證通過',
                'data' => [
                    'match' => true,
                    'asset_name' => $asset->name,
                    'asset_tag' => $asset->asset_tag
                ]
            ]);
        } else {
            // 為了安全性，我們可以查一下是哪個環節錯了，給前端提示 (可選)
            // 情況 A: 編號存在，但 EPC 不對
            $checkNumber = FixedAsset::where('remark', $request->custom_number)->first();
            
            if ($checkNumber) {
                return response()->json([
                    'status' => 'error',
                    'message' => '驗證失敗：編號存在，但 RFID 標籤不匹配！',
                    'data' => ['match' => false]
                ], 400); // 400 Bad Request
            }

            // 情況 B: 編號根本不存在
            return response()->json([
                'status' => 'error',
                'message' => '驗證失敗：查無此自定義編號',
                'data' => ['match' => false]
            ], 404);
        }
    }
}