<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// ▼▼▼ 1. 引入 Laravel 11 新版 Middleware 介面 ▼▼▼
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

// ▼▼▼ 2. 實作 HasMiddleware 介面 ▼▼▼
class FixedAssetController extends Controller implements HasMiddleware
{
    /**
     * Laravel 11+ 定義 Middleware 的新方法
     */
    public static function middleware(): array
    {
        return [
            // 所有人都能看
            new Middleware('permission:view assets', only: ['index', 'show', 'export']),
            
            // 只有授權者能管理
            new Middleware('permission:manage assets', only: ['store', 'update', 'destroy', 'batchImport']),
        ];
    }

    public function export()
    {
        $fileName = 'fixed_assets_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // 寫入 UTF-8 BOM，避免 Excel 開啟中文亂碼
            fwrite($handle, "\xEF\xBB\xBF");

            // 標題列：對應編輯頁面能看到的所有欄位
            fputcsv($handle, [
                '固資編號',
                '設備名稱',
                'RFID 標籤 ID',
                '保管人',
                '狀態',
                '廠區',
                '樓層',
                '區域',
                '存放位置',
            ]);

            // 防止 Excel 將 '-'、'+'、'='、'@' 開頭的字串當成公式（導致 #NAME?），或將長數字/前導零轉為科學記號
            $escapeForExcel = function ($value, bool $forceText = false) {
                if ($value === null || $value === '') {
                    return '';
                }
                $str = (string) $value;
                if ($forceText || preg_match('/^[\-\+\=\@]/', $str)) {
                    return '="' . str_replace('"', '""', $str) . '"';
                }
                return $str;
            };

            FixedAsset::with('responsibleUser')
                ->orderBy('id', 'asc')
                ->chunk(500, function ($assets) use ($handle, $escapeForExcel) {
                    foreach ($assets as $asset) {
                        fputcsv($handle, [
                            $escapeForExcel($asset->asset_tag, true),
                            $escapeForExcel($asset->name),
                            $escapeForExcel($asset->rfid_tag, true),
                            $escapeForExcel($asset->responsibleUser?->name ?? $asset->keeper ?? ''),
                            $escapeForExcel($asset->status),
                            $escapeForExcel($asset->factory_area),
                            $escapeForExcel($asset->floor),
                            $escapeForExcel($asset->area),
                            $escapeForExcel($asset->location),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function index(Request $request)
    {
        $query = FixedAsset::with('responsibleUser')->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('rfid_tag', 'like', "%{$search}%")
                  ->orWhereHas('responsibleUser', function($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('user_id') && $request->filled('user_id')) {
            $query->where('responsible_user_id', $request->user_id);
        }

        return $query->paginate(50);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string|unique:fixed_assets,asset_tag',
            'name' => 'required|string|max:255',
            'status' => 'required|string',
            'rfid_tag' => 'nullable|string|unique:fixed_assets,rfid_tag',
            'responsible_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = FixedAsset::create($request->all());
        return response()->json($asset->load('responsibleUser'), 201);
    }

    public function show($id)
    {
        return FixedAsset::with('responsibleUser')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string|unique:fixed_assets,asset_tag,' . $asset->id,
            'name' => 'required|string|max:255',
            'rfid_tag' => 'nullable|string|unique:fixed_assets,rfid_tag,' . $asset->id,
            'responsible_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset->update($request->all());
        return response()->json($asset->load('responsibleUser'));
    }

    public function destroy($id)
    {
        $asset = FixedAsset::findOrFail($id);
        $asset->delete();
        return response()->json(null, 204);
    }

    public function batchImport(Request $request)
    {
        return response()->json(['message' => '批量導入功能尚未啟用'], 501);
    }
}