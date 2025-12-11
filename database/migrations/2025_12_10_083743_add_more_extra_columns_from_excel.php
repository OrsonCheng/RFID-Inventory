<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // === 1. 核心識別 (Core Identity) ===
            // 'asset_tag' 已經有了
            // 'rfid_tag' 已經有了
            
            // 新增 PPID (如果還沒有的話)
            if (!Schema::hasColumn('fixed_assets', 'ppid')) {
                $table->string('ppid')->nullable()->index()->comment('PPID')->after('rfid_tag');
            }

            // === 2. 設備詳情 (Device Details) ===
            // 'name' 已經有了
            
            // 新增 規格說明
            if (!Schema::hasColumn('fixed_assets', 'spec')) {
                $table->text('spec')->nullable()->comment('規格說明')->after('name');
            }
            
            // 新增 品牌
            if (!Schema::hasColumn('fixed_assets', 'brand')) {
                $table->string('brand')->nullable()->comment('品牌')->after('spec');
            }
            
            // 'model' 已經有了，如果位置不對可以用 change() 移動，但通常沒必要
            // 如果你之前的 migration 沒加 model，這裡補上
            if (!Schema::hasColumn('fixed_assets', 'model')) {
                $table->string('model')->nullable()->comment('型號')->after('brand');
            }

            // === 3. 來源與日期 (Source & Date) ===
            // 新增 取得日期 (purchased_at)
            if (!Schema::hasColumn('fixed_assets', 'purchased_at')) {
                $table->date('purchased_at')->nullable()->comment('取得日期')->after('model');
            }
            
            // 新增 來源類型
            if (!Schema::hasColumn('fixed_assets', 'source_type')) {
                $table->string('source_type')->nullable()->comment('來源類型')->after('purchased_at');
            }

            // === 4. 組織架構 (Organization) ===
            // 新增 所屬公司
            if (!Schema::hasColumn('fixed_assets', 'company')) {
                $table->string('company')->nullable()->comment('所屬公司')->after('source_type');
            }
            
            // 新增 部門代碼
            if (!Schema::hasColumn('fixed_assets', 'department_code')) {
                $table->string('department_code')->nullable()->comment('部門代碼')->after('company');
            }
            
            // 新增 部門名稱
            if (!Schema::hasColumn('fixed_assets', 'department_name')) {
                $table->string('department_name')->nullable()->comment('部門名稱')->after('department_code');
            }

            // === 5. 位置資訊 (Location Info) ===
            // 新增 廠區
            if (!Schema::hasColumn('fixed_assets', 'factory_area')) {
                $table->string('factory_area')->nullable()->comment('廠區')->after('department_name');
            }
            
            // 新增 樓層
            if (!Schema::hasColumn('fixed_assets', 'floor')) {
                $table->string('floor')->nullable()->comment('樓層')->after('factory_area');
            }
            
            // 新增 綫別/區域
            if (!Schema::hasColumn('fixed_assets', 'area')) {
                $table->string('area')->nullable()->comment('綫別/區域')->after('floor');
            }
            
            // 'location' (站別/儲位) 已經有了

            // === 6. 管理資訊 (Management) ===
            // 新增 保管人 (純文字)
            if (!Schema::hasColumn('fixed_assets', 'keeper')) {
                $table->string('keeper')->nullable()->comment('保管人')->after('location');
            }
            
            // 'status' 已經有了
            // 'responsible_user_id' 已經有了

            // 新增 備註
            if (!Schema::hasColumn('fixed_assets', 'remark')) {
                $table->text('remark')->nullable()->comment('備註')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn([
                'ppid',
                'spec',
                'brand',
                // 'model', // 如果原本就有，就不要 drop
                'purchased_at',
                'source_type',
                'company',
                'department_code',
                'department_name',
                'factory_area',
                'floor',
                'area',
                'keeper',
                'remark',
            ]);
        });
    }
};