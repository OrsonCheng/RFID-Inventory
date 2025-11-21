<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    // 獲取資產列表 (支援搜尋與篩選)
    public function index(Request $request)
    {
        // 預設載入關聯並按時間倒序
        $query = FixedAsset::with('responsibleUser')->orderBy('created_at', 'desc');

        // 1. 關鍵字搜尋 (支援：資產名稱、編號、RFID、**保管人姓名**)
        if ($request->has('search') && $request->search) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('rfid_tag', 'like', "%{$search}%")
                  // 新增：關聯搜尋保管人姓名
                  ->orWhereHas('responsibleUser', function($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 2. 特定保管人篩選 (下拉選單專用)
        if ($request->has('user_id') && $request->user_id) {
            $query->where('responsible_user_id', $request->user_id);
        }
        
        return $query->paginate(50); // 每頁顯示 50 筆
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

    // 更新資產
    public function update(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string|unique:fixed_assets,asset_tag,' . $asset->id,
            'rfid_tag' => 'nullable|string|unique:fixed_assets,rfid_tag,' . $asset->id,
            'name' => 'required|string|max:255',
            'responsible_user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset->update($request->all());
        return response()->json($asset->load('responsibleUser'));
    }

    // 批量導入 (預留)
    public function batchImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx'
        ]);
        return response()->json(['message' => '批量導入功能尚未實作'], 501);
    }

    // 刪除資產
    public function destroy(FixedAsset $asset)
    {
        $asset->delete();
        return response()->json(null, 204);
    }
}