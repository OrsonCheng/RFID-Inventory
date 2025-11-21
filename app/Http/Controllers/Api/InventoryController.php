<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryWorkOrder;
use App\Models\InventoryWorkOrderItem;
use App\Models\FixedAsset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryController extends Controller
{
    // 獲取工單列表
    public function index()
    {
        return InventoryWorkOrder::with('responsibleUser', 'auditorUser')
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    // 創建盤點工單 (核心)
    public function store(Request $request)
    {
        $request->validate([
            'responsible_user_id' => 'required|exists:users,id',
            // 'auditor_user_id' => 'required|exists:users,id', // 盤點人可以稍後指派
        ]);

        $responsibleUser = User::findOrFail($request->input('responsible_user_id'));

        // 1. 查找該負責人名下所有 "在用" 或 "閒置" 的資產
        $assetsToInventory = FixedAsset::where('responsible_user_id', $responsibleUser->id)
                                    ->whereIn('status', ['使用中', '閒置'])
                                    ->get();

        if ($assetsToInventory->isEmpty()) {
            return response()->json(['message' => '該保管人名下沒有需要盤點的資產。'], 404);
        }

        return DB::transaction(function () use ($responsibleUser, $assetsToInventory, $request) {
            // 2. 創建工單表頭
            $workOrder = InventoryWorkOrder::create([
                'title' => Carbon::now()->toDateString() . ' - ' . $responsibleUser->name . ' 的盤點工單',
                'responsible_user_id' => $responsibleUser->id,
                'auditor_user_id' => $request->input('auditor_user_id'), // 可選
                'status' => 'pending',
            ]);

            // 3. 創建工單明細 (所有資產預設為 "未盤")
            $items = $assetsToInventory->map(function ($asset) use ($workOrder) {
                return [
                    'work_order_id' => $workOrder->id,
                    'fixed_asset_id' => $asset->id,
                    'status' => '未盤',
                ];
            });

            InventoryWorkOrderItem::insert($items->toArray());

            return response()->json($workOrder->load('responsibleUser'), 201);
        });
    }

    // 獲取單張工單詳情 (含所有明細)
    public function show($id)
    {
        $workOrder = InventoryWorkOrder::with(
            'responsibleUser',
            'auditorUser',
            'items.fixedAsset' //  eager load 資產資訊
        )->findOrFail($id);
        
        return response()->json($workOrder);
    }

    // 執行掃描 (核心)
    public function scan(Request $request, $id)
    {
        $request->validate([
            'rfid_tag' => 'required|string',
        ]);

        $workOrder = InventoryWorkOrder::findOrFail($id);
        $rfidTag = $request->input('rfid_tag');

        // 1. 根據 RFID 找到資產
        $asset = FixedAsset::where('rfid_tag', $rfidTag)->first();

        if (!$asset) {
            return response()->json(['message' => '錯誤：找不到對應此 RFID 的資產。', 'scanned_rfid' => $rfidTag], 404);
        }

        // 2. 在 *當前工單* 中查找該資產明細
        $item = $workOrder->items()
                         ->where('fixed_asset_id', $asset->id)
                         ->first();

        if (!$item) {
            return response()->json([
                'message' => '錯誤：此資產 ('.$asset->name.') 不屬於當前盤點工單。',
                'asset_name' => $asset->name,
                'asset_tag' => $asset->asset_tag
            ], 422); // 422 Unprocessable Entity
        }

        // 3. 更新狀態為 "已盤"
        if ($item->status === '未盤') {
            $item->status = '已盤';
            $item->scanned_at = Carbon::now();
            $item->save();
        }

        // 4. 返回更新後的項目 (包含資產資訊)
        return response()->json($item->load('fixedAsset'));
    }
}