<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // 只有 Admin 或 Senior (擁有管理權限) 才能看員工清單
            new Middleware('permission:manage assets|create work order', only: ['index']),
        ];
    }

    public function index()
    {
        return User::select('id', 'name')->get();
    }
}