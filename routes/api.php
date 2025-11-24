<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FixedAssetController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SSOController; // <--- 必須要有這一行！

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

Route::middleware('auth:sanctum')->group(function () {
	// 1. 資產管理 (綁定 RFID, 導入)
	Route::apiResource('assets', FixedAssetController::class);
	Route::post('assets/batch-import', [FixedAssetController::class, 'batchImport']);

	// 2. 用戶 (獲取保管人列表)
	Route::get('users', [UserController::class, 'index']);

	// 3. 盤點工單
	Route::get('inventory/work-orders', [InventoryController::class, 'index']);
	Route::post('inventory/work-orders', [InventoryController::class, 'store']); // 創建工單
	Route::get('inventory/work-orders/{id}', [InventoryController::class, 'show']);
	Route::post('inventory/work-orders/{id}/scan', [InventoryController::class, 'scan']); // 核心：掃描 RFID
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


