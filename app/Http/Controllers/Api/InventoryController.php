<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryWorkOrder;
use App\Models\InventoryWorkOrderItem;
use App\Models\FixedAsset;
use App\Models\User;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InventoryController extends Controller implements HasMiddleware
{
    /**
     * 定義權限 Middleware
     */
    public static function middleware(): array
    {
        return [
            // 1. 查看工單 & 執行盤點：開放給所有角色
            //new Middleware('permission:view work orders', only: ['index', 'show']),
            new Middleware('permission:scan items', only: ['scan']),
            
            // 2. 生成工單：只有 Admin 或 Senior Staff
            new Middleware('permission:create work order', only: ['store']),
        ];
    }

    public function index(Request $request)
	{
		return InventoryWorkOrder::with('responsibleUser', 'auditorUser')
			->withCount([
				'items', // 總數 (items_count)
				'items as scanned_count' => function ($query) {
					$query->where('status', '已盤');
				}
			])
			->orderBy('created_at', 'desc')
			->paginate(15);
	}

    public function store(Request $request)
    {
		$orderType = $request->input('order_type', 'monthly'); // 接收前端傳來的類型
		$titlePrefix = ($orderType === 'annual') ? '年度大盤' : '月度自盤';
		
        $request->validate([
            'responsible_user_id' => 'required|exists:users,id',
            'category' => 'nullable|string', 
        ]);

        $responsibleUser = User::findOrFail($request->input('responsible_user_id'));
        
        // 篩選資產邏輯
        $query = FixedAsset::where('responsible_user_id', $responsibleUser->id);
        
        $assetsToInventory = $query->get();

        if ($assetsToInventory->isEmpty()) {
            return response()->json(['message' => '該條件下沒有需要盤點的資產。'], 404);
        }

        return DB::transaction(function () use ($responsibleUser, $assetsToInventory, $titlePrefix, $request) {
            $title = Carbon::now()->toDateString() . ' ' . $responsibleUser->name . ' 盤點單';

            $workOrder = InventoryWorkOrder::create([
                'title' => Carbon::now()->toDateString() . " {$responsibleUser->name} - {$titlePrefix}",
                'responsible_user_id' => $responsibleUser->id,
                'auditor_user_id' => $request->user()->id, 
                'status' => 'pending',
            ]);

            $items = $assetsToInventory->map(function ($asset) use ($workOrder) {
                return [
                    'work_order_id' => $workOrder->id,
                    'fixed_asset_id' => $asset->id,
                    'status' => '未盤',
                    'scanner_user_id' => null,
                ];
            });

            InventoryWorkOrderItem::insert($items->toArray());

            return response()->json($workOrder->load('responsibleUser'), 201);
        });
    }

    public function show($id)
    {
        $workOrder = InventoryWorkOrder::with([
            'responsibleUser',
            'items.fixedAsset:id,asset_tag,rfid_tag,factory_area,floor,area,name,location',
            'items.scanner:id,name' 
        ])->findOrFail($id);
        
        return response()->json($workOrder);
    }
	
	// ★ 優化後的 Batch Scan：支援「自動找回」邏輯
    public function batchScan(Request $request, $id)
    {
        $request->validate(['rfid_tags' => 'required|array']);
        
        $workOrder = InventoryWorkOrder::findOrFail($id);
        $rfidTags = $request->input('rfid_tags');
        $currentUser = $request->user();

        $assets = FixedAsset::whereIn('rfid_tag', $rfidTags)->get()->keyBy('rfid_tag');
        
        $results = ['success' => [], 'failed' => []];

        foreach ($rfidTags as $tag) {
            if (!isset($assets[$tag])) {
                $results['failed'][] = ['tag' => $tag, 'reason' => '無效的標籤'];
                continue;
            }

            $asset = $assets[$tag];
            $item = $workOrder->items()->where('fixed_asset_id', $asset->id)->first();

            if (!$item) {
                $results['failed'][] = ['tag' => $tag, 'reason' => '非本單資產', 'asset_name' => $asset->name];
                continue;
            }

            // ★ 核心邏輯修改：
            // 如果原本是 '盤虧'，現在掃到了，這是一個「找回」事件，值得紀錄
            $isRecovery = ($item->status === '盤虧');

            if ($item->status !== '已盤') {
                $item->update([
                    'status' => '已盤',
                    'scanned_at' => Carbon::now(),
                    'scanner_user_id' => $currentUser->id,
                    'note' => null // 清除遺失原因 (因為找到了)
                ]);

                // 寫入日誌
                InventoryLog::create([
                    'inventory_work_order_id' => $workOrder->id,
                    'fixed_asset_id' => $asset->id,
                    'user_id' => $currentUser->id,
                    'action' => $isRecovery ? 'recover_found' : 'scan', // 區分是普通掃描還是找回
                    'note' => $isRecovery ? '掃描找回已報失資產' : '正常掃描'
                ]);
            }
            
            $results['success'][] = $item->load(['fixedAsset', 'scanner']);
        }

        $this->updateOrderStatus($workOrder); // 抽取共用方法更新狀態

        return response()->json($results);
    }

    // ★ 申報盤虧 (加入 Log)
    public function reportMissing(Request $request, $id)
    {
        $request->validate([
            'item_id' => 'required|exists:inventory_work_order_items,id',
            'reason' => 'required|string|max:255',
        ]);

        $workOrder = InventoryWorkOrder::findOrFail($id);
        $item = $workOrder->items()->where('id', $request->input('item_id'))->firstOrFail();

        if ($item->status === '已盤') {
            return response()->json(['message' => '該資產已盤點，無法標記遺失'], 422);
        }

        $reason = $request->input('reason');

        $item->update([
            'status' => '盤虧',
            'scanned_at' => null,
            'scanner_user_id' => $request->user()->id, // 記錄是誰報失的
            'note' => $reason
        ]);

        // ★ 寫入稽核日誌
        InventoryLog::create([
            'inventory_work_order_id' => $workOrder->id,
            'fixed_asset_id' => $item->fixed_asset_id,
            'user_id' => $request->user()->id,
            'action' => 'report_missing',
            'note' => $reason
        ]);

        $this->updateOrderStatus($workOrder);

        return response()->json($item->load('fixedAsset'));
    }

    // ★ 撤銷盤虧 (加入 Log)
    public function revertMissing(Request $request, $id)
    {
        $request->validate(['item_id' => 'required']);
        $workOrder = InventoryWorkOrder::findOrFail($id);
        $item = $workOrder->items()->where('id', $request->input('item_id'))->firstOrFail();

        if ($item->status !== '盤虧') {
            return response()->json(['message' => '該資產並非盤虧狀態'], 422);
        }

        // 先把舊的原因存起來寫入 log
        $oldReason = $item->note;

        $item->update([
            'status' => '未盤',
            'note' => null,
            'scanner_user_id' => null
        ]);

        // ★ 寫入稽核日誌：記錄這是「人為撤銷」，而非掃描找回
        InventoryLog::create([
            'inventory_work_order_id' => $workOrder->id,
            'fixed_asset_id' => $item->fixed_asset_id,
            'user_id' => $request->user()->id,
            'action' => 'revert_manual',
            'note' => "手動撤銷盤虧狀態 (原原因: $oldReason)"
        ]);
        
        $this->updateOrderStatus($workOrder);

        return response()->json($item->load('fixedAsset'));
    }

    // 輔助方法：更新工單整體狀態
    private function updateOrderStatus($workOrder)
    {
        // 只要有任何動作，就設為進行中 (除非全部完成)
        if ($workOrder->status === 'pending') {
            $workOrder->update(['status' => 'in_progress']);
        } 
        
        // 檢查是否全部完成 (已盤 + 盤虧 = 總數)
        // 也就是說「未盤」數量為 0
        $remaining = $workOrder->items()->where('status', '未盤')->count();
        
        if ($remaining === 0) {
            $workOrder->update(['status' => 'completed']);
        } else {
            // 如果有人手動撤銷導致又有未盤，且狀態是 completed，要改回 in_progress
            if ($workOrder->status === 'completed') {
                $workOrder->update(['status' => 'in_progress']);
            }
        }
    }

    // 為了相容舊 API 的 scan 方法 (如果還有人用的話)
    public function scan(Request $request, $id)
    {
        // 建議直接複用 batchScan 的邏輯，或簡單包裝一下
        $request->merge(['rfid_tags' => [$request->input('rfid_tag')]]);
        $response = $this->batchScan($request, $id);
        
        // 轉換回單筆格式 (為了相容前端舊版代碼，如果有)
        $data = $response->getData(true);
        if (!empty($data['failed'])) {
            return response()->json(['message' => $data['failed'][0]['reason']], 422);
        }
        return response()->json($data['success'][0]);
    }
	
	//取得工單日誌
    public function logs($id)
    {
        $logs = InventoryLog::with(['user', 'fixedAsset'])
            ->where('inventory_work_order_id', $id)
            ->orderBy('created_at', 'desc') // 最新在上面
            ->get();

        return response()->json($logs);
    }
}