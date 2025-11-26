<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FixedAsset;
use App\Models\InventoryWorkOrder;
use App\Models\InventoryWorkOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RealAssetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 清空舊資料 (重置資產與工單，但保留使用者與權限)
        Schema::disableForeignKeyConstraints();
        InventoryWorkOrderItem::truncate();
        InventoryWorkOrder::truncate();
        FixedAsset::truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info('舊資產與工單資料已清空。');

        // 2. 獲取預設保管人 (王洛飛)
        $owner = User::where('name', '王洛飛')->first();
        
        if (!$owner) {
            // 如果找不到，就用第一位使用者，或建立一個
            $owner = User::first() ?? User::create([
                'name' => '王洛飛',
                'email' => 'wang@te.com',
                'password' => bcrypt('password')
            ]);
        }

        // 3. 真實 RFID 數據 (模擬對應的儀器名稱)
        $realAssets = [
            ['rfid' => 'C02400000206', 'name' => 'Keysight 數位電錶', 'tag' => 'F-202300001', 'loc' => '三廠2F-A區'],
            ['rfid' => 'C02400000208', 'name' => 'HIOKI 內阻計', 'tag' => 'F-202300002', 'loc' => '三廠2F-B區'],
            ['rfid' => 'C02400000374', 'name' => 'Chroma 直流電源', 'tag' => 'F-202300003', 'loc' => '維修中心'],
            ['rfid' => 'C02400000139', 'name' => 'Tektronix 示波器', 'tag' => 'F-202300004', 'loc' => '三廠3F-實驗室'],
            ['rfid' => 'C02400000202', 'name' => 'LCR 測試儀', 'tag' => 'F-202300005', 'loc' => '三廠2F-A區'],
            ['rfid' => 'C02400001358', 'name' => '836 自製模組', 'tag' => 'F-202300006', 'loc' => '三廠2F-倉庫'],
            ['rfid' => 'C02400000209', 'name' => '工業電腦 IPC', 'tag' => 'F-202300007', 'loc' => '產線-Line1'],
            ['rfid' => 'C02400000140', 'name' => '條碼掃描槍', 'tag' => 'F-202300008', 'loc' => '產線-Line1'],
            ['rfid' => 'C02400000205', 'name' => '扭力測試機', 'tag' => 'F-202300009', 'loc' => '三廠3F-QA'],
            ['rfid' => 'C02400001392', 'name' => '絕緣耐壓測試機', 'tag' => 'F-202300010', 'loc' => '三廠2F-C區'],
            ['rfid' => 'C02400000207', 'name' => '電子負載', 'tag' => 'F-202300011', 'loc' => '維修中心'],
        ];

        foreach ($realAssets as $data) {
            FixedAsset::create([
                'asset_tag' => $data['tag'],
                'name' => $data['name'],
                'rfid_tag' => $data['rfid'], // 寫入真實 RFID
                'status' => '使用中',
                //'category' => 'F1',
                'location' => $data['loc'],
                'responsible_user_id' => $owner->id,
                //'brand' => 'Simplo-Test', // 模擬品牌
            ]);
        }

        $this->command->info('成功導入 ' . count($realAssets) . ' 筆真實 RFID 資產！');
    }
}