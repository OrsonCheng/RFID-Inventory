<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ChannelIdSequence;

class ChannelController extends Controller
{
    /**
     * 發放唯一的 Channel ID
     * 使用 firstOrCreate + lockForUpdate 防止多台電腦同時取號重複
     */
    public function generateId(Request $request)
    {
        $request->validate([
            'MachineTypePrefix' => 'required|string|max:2',
        ]);

        $prefix = strtoupper($request->MachineTypePrefix);

        try {
            $newChannelId = DB::transaction(function () use ($prefix) {
                // 1. 確保此機型有紀錄 (firstOrCreate 內部有防併發處理)
                ChannelIdSequence::firstOrCreate(
                    ['prefix_type' => $prefix],
                    ['current_seq' => 0]
                );

                // 2. 重新鎖定該筆紀錄，確保遞增互斥
                $sequence = ChannelIdSequence::where('prefix_type', $prefix)
                    ->lockForUpdate()
                    ->first();

                $sequence->current_seq += 1;
                $sequence->save();

                // 超出最大容量 (0xFFFF = 65535) 則回側錯誤
                if ($sequence->current_seq > 0xFFFF) {
                    throw new \Exception('Channel ID pool exhausted for prefix: ' . $prefix);
                }

                // 4 位 HEX 大寫，例如 A0001, AFFFF
                return $prefix . strtoupper(str_pad(dechex($sequence->current_seq), 4, '0', STR_PAD_LEFT));
            });
			
            return response()->json([
                'Success'   => true,
                'ChannelID' => $newChannelId,
                'Message'   => 'ID Generated Successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'Success' => false,
                'Message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}
