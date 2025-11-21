<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\FixedAsset;
use App\Models\InventoryWorkOrder;
use App\Models\InventoryWorkOrderItem;
use Spatie\Permission\Models\Role;       
use Spatie\Permission\Models\Permission; 

use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
class RfidSeeder extends Seeder
{
     public function run(): void
    {
        // 1. 清空舊資料 (避免重複執行時報錯)
        Schema::disableForeignKeyConstraints();
        InventoryWorkOrderItem::truncate();
        InventoryWorkOrder::truncate();
        FixedAsset::truncate();
        User::truncate();
        Role::truncate();
        Permission::truncate();
        Schema::enableForeignKeyConstraints();

        // 2. 建立權限與角色
        // 建立基本權限
        $permCreateAsset = Permission::create(['name' => 'create assets']); // 建立資產
        $permScanInventory = Permission::create(['name' => 'scan inventory']); // 執行盤點
        
        // 建立角色
        $roleAdmin = Role::create(['name' => 'admin']); // 管理員
        $roleStaff = Role::create(['name' => 'staff']); // 一般員工

        // 分配權限給角色
        $roleAdmin->givePermissionTo([$permCreateAsset, $permScanInventory]); // 管理員甚麼都能做
        $roleStaff->givePermissionTo([$permScanInventory]); // 員工只能盤點

        // 3. 建立使用者並分配角色

        // --- 系統管理員 ---
        $userAdmin = User::create([
            'name' => '系統管理員',
            'email' => 'admin@te.com',
            'password' => bcrypt('password'),
        ]);
        $userAdmin->assignRole($roleAdmin); // <--- 分配 Admin 角色

        // --- 王洛飛 (保管人/員工) ---
        $userWang = User::create([
            'name' => '王洛飛',
            'email' => 'wang@te.com',
            'password' => bcrypt('password'),
        ]);
        $userWang->assignRole($roleStaff); // <--- 分配 Staff 角色

        // --- 李志剛 (保管人/員工) ---
        $userLi = User::create([
            'name' => '李志剛',
            'email' => 'li@te.com',
            'password' => bcrypt('password'),
        ]);
        $userLi->assignRole($roleStaff);

        // 4. 建立固定資產 (範例資料)
        
        // 王洛飛的資產
        $assetsWang = [
            [
                'asset_tag' => 'F-2014000640',
                'name' => '內阻計 IR 3561',
                'rfid_tag' => 'RFID-1001', // 測試用 RFID
                'status' => '使用中',
                'location' => '三廠二樓-X3671-CC34-Wakeup',
            ],
            [
                'asset_tag' => 'F-201400005',
                'name' => '精密電錶 34401A',
                'rfid_tag' => 'RFID-1002',
                'status' => '使用中',
                'location' => '三廠二樓-設備間',
            ],
            [
                'asset_tag' => 'F-202200555',
                'name' => '836模組 (自製)',
                'rfid_tag' => 'RFID-1003',
                'status' => '閒置',
                'location' => '三廠二樓-倉庫',
            ],
            [
                'asset_tag' => 'F-202300001',
                'name' => '數據採集器 34970A',
                'rfid_tag' => null, // 未綁定
                'status' => '使用中',
                'location' => '三廠二樓-實驗室',
            ],
        ];

        foreach ($assetsWang as $data) {
            FixedAsset::create(array_merge($data, ['responsible_user_id' => $userWang->id]));
        }

        // 李志剛的資產
        $assetsLi = [
            [
                'asset_tag' => 'F-201900111',
                'name' => 'Fluke 萬用表',
                'rfid_tag' => 'RFID-2001',
                'status' => '使用中',
                'location' => '維修工具車-第2格',
            ],
            [
                'asset_tag' => 'F-201900222',
                'name' => '電烙鐵焊台',
                'rfid_tag' => null,
                'status' => '使用中',
                'location' => '維修房',
            ],
        ];

        foreach ($assetsLi as $data) {
            FixedAsset::create(array_merge($data, ['responsible_user_id' => $userLi->id]));
        }

        // 5. 建立測試工單
        // 自動生成一張「王洛飛」的盤點工單
        $workOrder = InventoryWorkOrder::create([
            'title' => Carbon::now()->toDateString() . ' 王洛飛-季度盤點 (測試單)',
            'responsible_user_id' => $userWang->id,
            'status' => 'pending',
        ]);

        // 將王洛飛名下 "使用中" 或 "閒置" 的資產加入工單
        $wangAssets = FixedAsset::where('responsible_user_id', $userWang->id)
            ->whereIn('status', ['使用中', '閒置'])
            ->get();

        foreach ($wangAssets as $asset) {
            InventoryWorkOrderItem::create([
                'work_order_id' => $workOrder->id,
                'fixed_asset_id' => $asset->id,
                'status' => '未盤', // 初始狀態
            ]);
        }

        echo "--------------------------------------------------\n";
        echo "權限與資料生成完畢！\n";
        echo "Admin: admin@te.com (擁有所有權限)\n";
        echo "User : wang@te.com (僅盤點權限)\n";
        echo "--------------------------------------------------\n";
        echo "測試用 RFID 代碼 (在盤點頁面輸入):\n";
        echo "1. RFID-1001 (內阻計)\n";
        echo "2. RFID-1002 (精密電錶)\n";
        echo "3. RFID-1003 (836模組)\n";
        echo "--------------------------------------------------\n";
    }
}