<?php

namespace App\Http\Controllers\Api; // ★★★ 關鍵：Namespace 必須正確

use App\Http\Controllers\Controller;
use Illuminate\Http\Request; 
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SSOController extends Controller
{
    // 1. 重新導向到 TE Auth Center 登入頁
    public function redirect()
    {
        // 使用 laravelpassport 驅動 (對應 config/services.php)
        return Socialite::driver('laravelpassport')->stateless()->redirect();
    }

    // 2. 處理 Auth Center 回傳的授權碼 (Callback)
    public function callback()
    {
        try {
            // 向 Auth Center 換取使用者資料
            // stateless() 很重要，因為 API 通常沒有 session
            $ssoUser = Socialite::driver('laravelpassport')->stateless()->user();
            
            // $ssoUser 包含遠端使用者的: id, name, email ...
            
            // 在本地資料庫查找或建立使用者
            // 注意：這裡我們用 email 當作唯一識別
            $user = User::updateOrCreate(
                ['email' => $ssoUser->getEmail()],
                [
                    'name' => $ssoUser->getName(),
                    // 本地不需要密碼，因為是靠 SSO 驗證，給個亂數密碼即可
                    'password' => bcrypt(Str::random(24)), 
                ]
            );

            // 如果是用戶第一次登入，自動給予基本權限 (選用)
            if ($user->roles->isEmpty()) {
                $user->assignRole('staff'); // 假設您有 Spatie Permission
            }

            // 產生本地 API Token (讓前端 Vue 使用)
            // 這是 Sanctum 的功能
            $user->tokens()->delete(); // (選用) 保持單一裝置登入，刪除舊 Token
            $token = $user->createToken('sso-token')->plainTextToken;

            // ★★★ 關鍵跳轉 ★★★
            // 因為這是瀏覽器行為，我們不能回傳 JSON
            // 必須把 Token 帶在網址上，跳轉回 Vue 的前端接收頁面
            // Vue 頁面路徑: resources/js/Pages/AuthCallback.vue
            // 網址範例: http://127.0.0.1:8000/auth/callback?token=xxxxx
            return redirect("http://127.0.0.1:8000/auth/callback?token={$token}");

        } catch (\Exception $e) {
            return response()->json(['error' => 'SSO Login Failed: ' . $e->getMessage()], 500);
        }
    }

	 // 3. 處理 SSO 登出
    public function logout(Request $request)
    {		
		// A. 清除 API Token (Sanctum)
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        // B. 清除 Web Session (如果有)
        // ▼▼▼ 修正 2: 加入 hasSession() 檢查，防止在 API 路由報錯 ▼▼▼
        if ($request->hasSession()) {
            // 只有在請求有 Session 時才執行 Session 清除
            // 這樣可以兼容 routes/api.php (無 Session) 和 routes/web.php (有 Session)
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // C. 準備 SSO Server 的登出網址
        $ssoHost = config('services.laravelpassport.host'); 
		


        if (!$ssoHost) {
            return response()->json(['message' => 'Logged out locally', 'logout_url' => null]);
        }

        $frontendUrl = config('app.url', 'http://127.0.0.1:8000');
        $returnTo = $frontendUrl . '/login';

        // http://sso-server.test/sso/logout?return_to=http://client.test/login
        $redirectUrl = $ssoHost . '/logout?redirect=' . urlencode($returnTo);

        return response()->json([
            'message' => 'Logged out',
            'logout_url' => $redirectUrl
        ]);
    }
}
