<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
		Schema::create('fixed_assets', function (Blueprint $table) {
			$table->id();
			
			// 核心欄位
			$table->string('asset_tag')->unique()->comment('固定資產編號');
			$table->string('rfid_tag')->unique()->nullable()->index()->comment('RFID 標籤編號');
			$table->string('name')->comment('設備名稱');
			$table->string('model')->nullable()->comment('型號');
			$table->string('status')->default('使用中')->comment('資產狀態: 使用中, 閒置, 維修中, 已報廢');
			
			// 負責人 (保管人)
			$table->foreignId('responsible_user_id')->nullable()->constrained('users')->comment('保管人/負責人 ID');
			$table->timestamps(); // <--- 必須要有這一行！它會產生 created_at 和 updated_at
			// 其他補充欄位 (來自 CSV)
			$table->string('location')->nullable()->comment('存放位置');
			
		});

        Schema::create('inventory_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('工單標題 (例如: 2025-11-20 王洛飛的盤點工單)');
            
            // 此次盤點的對象 (負責人)
            $table->foreignId('responsible_user_id')->constrained('users')->comment('資產保管人 ID');
            
            // 執行盤點的人
            $table->foreignId('auditor_user_id')->nullable()->constrained('users')->comment('盤點執行人 ID');
            
            $table->string('status')->default('pending')->comment('工單狀態: pending, in_progress, completed');
            $table->timestamps();
        });

        Schema::create('inventory_work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('inventory_work_orders')->onDelete('cascade');
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->onDelete('cascade');
            
            $table->string('status')->default('未盤')->comment('盤點狀態: 未盤, 已盤');
            $table->timestamp('scanned_at')->nullable()->comment('掃描時間');
            
            // 確保同一張工單的同一個資產只有一筆
            $table->unique(['work_order_id', 'fixed_asset_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('inventory_work_order_items');
        Schema::dropIfExists('inventory_work_orders');
        Schema::dropIfExists('fixed_assets');
    }
};