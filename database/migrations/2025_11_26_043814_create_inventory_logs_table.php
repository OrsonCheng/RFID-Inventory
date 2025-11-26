<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_work_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('fixed_asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->comment('操作人員'); // 執行此動作的人
            $table->string('action'); // 動作類型: 'scan', 'report_missing', 'revert_missing'
            $table->string('note')->nullable(); // 備註 (例如遺失原因)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};