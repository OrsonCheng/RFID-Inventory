<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalibrationMaster;
use Illuminate\Http\Request;

class CalibrationController extends Controller
{
    /**
     * 取得校驗主紀錄列表 (含分頁與搜尋)
     */
    public function index(Request $request)
    {
        $query = CalibrationMaster::query();

        // 搜尋條件：機台編號、通道 ID 或 員工工號
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('channel_id', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        // 以測試開始時間降序排序
        $query->orderBy('start_time', 'desc');

        // 進行分頁，並預先載入明細資料
        $calibrations = $query->with(['details', 'preVerifyDetails'])->paginate(50);

        return response()->json($calibrations);
    }
}
