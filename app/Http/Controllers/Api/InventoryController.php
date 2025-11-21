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

    // 創建盤點工單
    public function store(Request $request)
    {
        $request->validate([
            'responsible_user_id' => 'required|exists:users,id',
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
                'auditor_user_id' => $request->input('auditor_user_id'),
                'status' => 'pending', // 初始狀態
            ]);

            // 3. 創建工單明細
            $items = $assetsToInventory->map(function ($asset) use ($workOrder) {
                return [
                    'work_order_id' => $workOrder->id,
                    'fixed_asset_id' => $asset->id,
                    'status' => '未盤',
                    'scanned_at' => null,
                ];
            });

            InventoryWorkOrderItem::insert($items->toArray());

            return response()->json($workOrder->load('responsibleUser'), 201);
        });
    }

    // 獲取單張工單詳情
    public function show($id)
    {
        $workOrder = InventoryWorkOrder::with(
            'responsibleUser',
            'auditorUser',
            'items.fixedAsset'
        )->findOrFail($id);
        
        return response()->json($workOrder);
    }

    // 執行掃描 (核心邏輯修改)
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

        // 2. 在當前工單中查找該資產明細
        $item = $workOrder->items()
                         ->where('fixed_asset_id', $asset->id)
                         ->first();

        if (!$item) {
            return response()->json([
                'message' => '錯誤：此資產 ('.$asset->name.') 不屬於當前盤點工單。',
                'asset_name' => $asset->name,
                'asset_tag' => $asset->asset_tag
            ], 422);
        }

        // 3. 更新狀態為 "已盤"
        if ($item->status === '未盤') {
            $item->status = '已盤';
            $item->scanned_at = Carbon::now();
            $item->save();
        }

        // ==========================================
        // ▼▼▼ 新增邏輯：自動更新工單狀態 ▼▼▼
        // ==========================================
        
        // 檢查是否還有 "未盤" 的項目
        $remainingCount = $workOrder->items()->where('status', '未盤')->count();

        if ($remainingCount === 0) {
            // 如果沒有未盤項目，標記工單為完成
            if ($workOrder->status !== 'completed') {
                $workOrder->update(['status' => 'completed']);
            }
        } else {
            // 如果還有未盤項目，但工單還是 pending，改為進行中 (in_progress)
            if ($workOrder->status === 'pending') {
                $workOrder->update(['status' => 'in_progress']);
            }
        }

        // 4. 返回更新後的項目
        return response()->json($item->load('fixedAsset'));
    }
}