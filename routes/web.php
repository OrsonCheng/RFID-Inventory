<?php

use Illuminate\Support\Facades\Route;

// 這是原本的首頁
// Route::get('/', function () {
//     return view('welcome');
// });

// ▼▼▼ 改成這樣 ▼▼▼
// 設定一個 "Catch-all" 路由。
// 意思是：除了 API 路由以外的所有網址，都回傳 'welcome' 視圖，
// 讓 Vue Router 接手處理畫面切換。
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');