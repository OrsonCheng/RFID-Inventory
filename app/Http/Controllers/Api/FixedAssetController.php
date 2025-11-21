<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    // 獲取資產列表 (可帶查詢)
    public function index(Request $request)
    {
        $query = FixedAsset::with('responsibleUser');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('rfid_tag', 'like', "%{$search}%");
            });
        }
        
        return $query->paginate(20);
    }

    // 創建單筆資產
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string|unique:fixed_assets,asset_tag',
            'rfid_tag' => 'nullable|string|unique:fixed_assets,rfid_tag',
            'name' => 'required|string|max:255',
            'responsible_user_id' => 'required|exists:users,id',
            'status' => 'required|string',
            // ... 其他欄位驗證
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = FixedAsset::create($request->all());
        return response()->json($asset->load('responsibleUser'), 201);
    }

    // 獲取單筆資產
    public function show($id)
    {
        return FixedAsset::with('responsibleUser')->findOrFail($id);
    }

    // 更新資產 (核心：用於綁定 RFID)
    public function update(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string|unique:fixed_assets,asset_tag,' . $asset->id,
            'rfid_tag' => 'nullable|string|unique:fixed_assets,rfid_tag,' . $asset->id,
            'name' => 'required|string|max:255',
            'responsible_user_id' => 'required|exists:users,id',
            // ...
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset->update($request->all());
        return response()->json($asset->load('responsibleUser'));
    }

    // 批量導入
    public function batchImport(Request $request)
    {
        // 驗證上傳的檔案 (例如 CSV)
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx'
        ]);

        // *** 強烈建議 ***
        // 這裡的導入邏輯應使用 Laravel Queues (隊列) 在後台執行
        // 為了演示，這裡先用同步處理
        
        // (省略 CSV 解析邏輯)
        // 假設 $data 是一個從 CSV 解析來的陣列：
        // $data = [
        //   ['asset_tag' => 'F-001', 'name' => '電腦A', 'responsible_user_email' => 'user@a.com'],
        //   ['asset_tag' => 'F-002', 'name' => '電腦B', 'responsible_user_email' => 'user@b.com'],
        // ]

        // $importedCount = 0;
        // foreach ($data as $row) {
        //    $user = User::where('email', $row['responsible_user_email'])->first();
        //    if ($user) {
        //        FixedAsset::updateOrCreate(
        //            ['asset_tag' => $row['asset_tag']],
        //            [
        //                'name' => $row['name'],
        //                'responsible_user_id' => $user->id,
        //                // ... 其他欄位
        //            ]
        //        );
        //        $importedCount++;
        //    }
        // }
        // return response()->json(['message' => "成功導入 {$importedCount} 筆資料"]);
        
        // 提示：請實作導入邏輯
        return response()->json(['message' => '批量導入 API 已收到，請實作後續 CSV 解析與隊列處理邏輯。'], 501);
    }

    // 刪除資產 (可選)
    public function destroy(FixedAsset $asset)
    {
        $asset->delete();
        return response()->json(null, 204);
    }
}