<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FixedAssetController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\UserController;

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

