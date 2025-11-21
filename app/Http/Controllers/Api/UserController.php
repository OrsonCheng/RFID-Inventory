<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 獲取所有用戶列表 (用於下拉選單)
     */
    public function index()
    {
        // 僅返回 ID 和 Name，用於下拉選單
        return User::select('id', 'name')->get();
    }
}