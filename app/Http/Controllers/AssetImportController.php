<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            
            return back()->with('success', '資產匯入成功！');
        } catch (\Exception $e) {
            return back()->with('error', '匯入失敗: ' . $e->getMessage());
        }
    }
}