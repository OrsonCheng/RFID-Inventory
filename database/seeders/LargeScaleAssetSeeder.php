<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\FixedAsset;
use App\Models\InventoryWorkOrder;
use App\Models\InventoryWorkOrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LargeScaleAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. 獲取現有使用者 (確保 DatabaseSeeder 已經執行過)
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->error('錯誤：找不到任何使用者。請先執行 php artisan db:seed 建立基礎資料。');
            return;
        }

        $this->command->info('正在準備生成 600 筆模擬資產數據...');

        // 2. 準備擬真數據字典 (讓數據看起來像真的)
        $deviceTypes = [
            ['name' => '筆記型電腦', 'brands' => ['Lenovo', 'Dell', 'HP', 'Apple'], 'models' => ['ThinkPad X1', 'Latitude 7420', 'EliteBook', 'MacBook Pro']],
            ['name' => '24吋顯示器', 'brands' => ['Dell', 'ViewSonic', 'BenQ', 'ASUS'], 'models' => ['P2419H', 'VA2459', 'GW2480', 'ProArt']],
            ['name' => '示波器', 'brands' => ['Tektronix', 'Keysight', 'Rigol'], 'models' => ['TBS1000C', 'DSOX1000', 'DS1054Z']],
            ['name' => '直流電源供應器', 'brands' => ['GW Instek', 'Chroma', 'Keithley'], 'models' => ['GPD-3303S', '62000P', '2230G']],
            ['name' => '數位三用電表', 'brands' => ['Fluke', 'HIOKI', 'Sanwa'], 'models' => ['179', 'DT4256', 'CD800a']],
            ['name' => '條碼掃描槍', 'brands' => ['Zebra', 'Honeywell'], 'models' => ['DS2208', '1900G']],
            ['name' => '人體工學椅', 'brands' => ['Herman Miller', 'Ergohuman', 'IKEA'], 'models' => ['Aeron', 'Enjoy 121', 'Markus']],
            ['name' => '會議桌', 'brands' => ['OA-Office'], 'models' => ['Meeting-L', 'Meeting-S']],
            ['name' => '伺服器主機', 'brands' => ['Dell', 'HPE'], 'models' => ['PowerEdge R740', 'ProLiant DL380']],
            ['name' => 'LCR 測試儀', 'brands' => ['HIOKI', 'Keysight'], 'models' => ['IM3536', 'E4980A']],
        ];

        $locations = [
            '三廠二樓-研發辦公室A區', '三廠二樓-研發辦公室B區', 
            '三廠二樓-硬體實驗室', '三廠二樓-軟體測試區', 
            '三廠三樓-會議室1', '三廠三樓-資材倉庫', 
            '一廠一樓-SMT產線', '一廠一樓-維修中心'
        ];

        $statuses = ['使用中', '使用中', '使用中', '閒置', '維修中', '已報廢']; // 權重：大部分是使用中

        $assetsData = [];
        $now = Carbon::now();
        $userIds = $users->pluck('id')->toArray();

        // 3. 生成 600 筆資產
        for ($i = 1; $i <= 600; $i++) {
            $type = $deviceTypes[array_rand($deviceTypes)];
            $brand = $type['brands'][array_rand($type['brands'])];
            $model = $type['models'][array_rand($type['models'])];
            
            // 生成編號：F-2025 + 隨機流水號 (從 5000 開始，避免跟現有衝突)
            $assetTag = 'F-' . $now->year . str_pad((string)($i + 5000), 5, '0', STR_PAD_LEFT);
            
            // 85% 機率有 RFID，15% 機率未綁定
            $hasRfid = rand(1, 100) <= 85;
            $rfidTag = $hasRfid ? 'RFID-' . strtoupper(substr(md5($assetTag . rand()), 0, 8)) : null;

            $assetsData[] = [
                'asset_tag' => $assetTag,
                'name' => $brand . ' ' . $type['name'],
                'rfid_tag' => $rfidTag,
                'status' => $statuses[array_rand($statuses)],
                'location' => $locations[array_rand($locations)],
                'responsible_user_id' => $userIds[array_rand($userIds)], // 隨機分配給現有人員
                //'brand' => $brand,
                //'model' => $model,
                //'supplier' => $brand . ' 代理商',
                //'purchase_date' => $now->copy()->subDays(rand(30, 1500)), // 隨機購買日期
                //'price' => rand(2000, 150000), // 隨機價格
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 4. 批量插入 (Chunk Insert) 以提高效能
        foreach (array_chunk($assetsData, 100) as $chunk) {
            FixedAsset::insert($chunk);
        }

        $this->command->info('資產數據生成完畢！');

        // 5. 為 "王洛飛" (或第一個使用者) 建立一張超大的盤點工單
        $targetUser = User::where('name', '王洛飛')->first() ?? $users->first();
        
        if ($targetUser) {
            $this->command->info("正在為 {$targetUser->name} 生成大型測試工單...");

            $workOrder = InventoryWorkOrder::create([
                'title' => Carbon::now()->toDateString() . " {$targetUser->name} - 年度總盤點 (大量數據測試)",
                'responsible_user_id' => $targetUser->id,
                'status' => 'pending',
            ]);

            // 找出該使用者名下所有資產
            $userAssets = FixedAsset::where('responsible_user_id', $targetUser->id)
                ->whereIn('status', ['使用中', '閒置'])
                ->get();

            $itemsData = [];
            foreach ($userAssets as $asset) {
                $itemsData[] = [
                    'work_order_id' => $workOrder->id,
                    'fixed_asset_id' => $asset->id,
                    'status' => '未盤',
                    'scanned_at' => null,
                ];
            }

            // 批量插入工單項目
            foreach (array_chunk($itemsData, 100) as $chunk) {
                InventoryWorkOrderItem::insert($chunk);
            }

            $this->command->info("工單生成完畢！工單包含 " . count($itemsData) . " 筆資產。");
        }
    }
}