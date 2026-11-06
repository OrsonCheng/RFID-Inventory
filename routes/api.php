<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FixedAssetController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SSOController; 
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\Api\RfidController;
use App\Http\Controllers\Api\CalibrationController;
use App\Http\Controllers\Api\ChannelController;

/*
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/** C# 工作站使用 **/
Route::get('/assets/rfid-lookup', [RfidController::class, 'lookup']);//輸入資產編號 找RFID
Route::post('/assets/bind-rfid', [RfidController::class, 'bind']);//綁定 rfid 與 自定義編號
Route::post('/assets/verify', [RfidController::class, 'verify']);//驗證rfid 與 自定義編號一致

Route::post('/Channel/GenerateID', [ChannelController::class, 'generateId']);//自動校正 產生learning 機通道ID

Route::middleware('auth:sanctum')->group(function () {
	// 1. 資產管理 (綁定 RFID, 導入, 導出)
	Route::get('assets/export', [FixedAssetController::class, 'export']);
	Route::apiResource('assets', FixedAssetController::class);
	Route::post('assets/batch-import', [FixedAssetController::class, 'batchImport']);

	// 2. 用戶 (獲取保管人列表)
	Route::get('users', [UserController::class, 'index']);

	// 3. 盤點工單
	Route::get('inventory/work-orders', [InventoryController::class, 'index']);
	Route::post('inventory/work-orders', [InventoryController::class, 'store']); // 創建工單
	Route::get('inventory/work-orders/{id}', [InventoryController::class, 'show']);
	Route::post('inventory/work-orders/{id}/scan', [InventoryController::class, 'scan']); // 核心：掃描 RFID
	// 在 inventory routes 區域加入：
	Route::post('inventory/work-orders/{id}/batch-scan', [InventoryController::class, 'batchScan']);
	//申報盤虧 (新增)
    Route::post('inventory/work-orders/{id}/report-missing', [InventoryController::class, 'reportMissing']);
    
    //撤銷盤虧 (新增)
    Route::post('inventory/work-orders/{id}/revert-missing', [InventoryController::class, 'revertMissing']);
    //取得稽核日誌 (新增)
    Route::get('inventory/work-orders/{id}/logs', [InventoryController::class, 'logs']);
    //導出盤點工單 Excel (新增)
    Route::get('inventory/work-orders/{id}/export', [InventoryController::class, 'export']);
	
	
	 // ★★★ 修改這段：獲取使用者資料時，順便載入角色與權限 ★★★
    Route::get('/user', function (Illuminate\Http\Request $request) {
        $user = $request->user();
        
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // 讓前端可以讀取 roles 陣列 (例如 ['admin'])
            'roles' => $user->getRoleNames(), 
            // 讓前端可以讀取 permissions 陣列 (例如 ['manage assets', 'scan items'])
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    });
	
	// 資產匯入路由
	Route::post('/assets/import', [AssetImportController::class, 'import']);

	// 校驗紀錄路由
	Route::get('/calibrations', [CalibrationController::class, 'index']);
});

// SSO 登出 (API)
// 當 Vue 呼叫 axios.post('/api/logout') 時，會執行 SSOController 的 logout 方法
// 這會回傳 { "logout_url": "http://sso-server/sso/logout..." }
Route::middleware('auth:sanctum')->post('/logout', [SSOController::class, 'logout']);



Route::prefix('auth')->group(function () {
    
    // 1. 啟動登入 (Redirect)
    // 前端點擊「使用 AD 登入」按鈕時，呼叫此 API
    // 網址範例: http://127.0.0.1:8000/api/auth/redirect
    Route::get('/redirect', [SSOController::class, 'redirect']);

    // 2. 登入回調 (Callback)
    // 用戶在 Auth Center 登入成功後，會被導向回這裡
    // 網址範例: http://127.0.0.1:8000/api/auth/callback?code=...
    Route::get('/callback', [SSOController::class, 'callback']);
    
});

// SSO 登出 (需要 auth:sanctum middleware，因為要撤銷 Token)
Route::middleware('auth:sanctum')->post('/logout', [SSOController::class, 'logout']);




