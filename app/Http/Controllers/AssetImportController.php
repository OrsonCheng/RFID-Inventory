<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\FixedAssetsImport;

class AssetImportController extends Controller
{
    public function import(Request $request)
    {
        // 驗證是否有上傳檔案
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            // 執行匯入
            Excel::import(new FixedAssetsImport, $request->file('file'));

            // 做法 B：批量匯入完成後，於 HTTP 回應送出後在背景自動執行 RFID 標籤生成
            dispatch(function () {
                Artisan::call('asset:generate-rfid');
            })->afterResponse();
            
            return back()->with('success', '資產匯入成功！系統正在背景自動生成 RFID 標籤。');
        } catch (\Exception $e) {
            return back()->with('error', '匯入失敗: ' . $e->getMessage());
        }
    }
}