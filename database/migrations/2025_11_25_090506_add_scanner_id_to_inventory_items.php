<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_work_order_items', function (Blueprint $table) {
            // 新增欄位：記錄實際執行掃描的人
            $table->foreignId('scanner_user_id')
                  ->nullable()
                  ->after('status')
                  ->constrained('users')
                  ->comment('實際掃描人員ID');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_work_order_items', function (Blueprint $table) {
            $table->dropForeign(['scanner_user_id']);
            $table->dropColumn('scanner_user_id');
        });
    }
};