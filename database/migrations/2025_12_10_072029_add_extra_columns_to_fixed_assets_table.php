<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // 這裡補上 CSV 中有，但你原本表中沒有的欄位
            
            // 建議加上 ->after('xxx') 讓欄位順序比較好看 (MySQL支援)
            $table->string('brand')->nullable()->comment('品牌')->after('name');
            $table->string('serial_number')->nullable()->comment('序列號')->after('model');
            $table->string('unit')->nullable()->comment('計量單位')->after('serial_number');
            $table->string('department')->nullable()->comment('部門')->after('location');
            
            // 保留原本的 responsible_user_id，新增純文字的 keeper 欄位以對應 CSV
            $table->string('keeper')->nullable()->comment('保管人(原始文字)')->after('department');
            
            $table->date('purchased_at')->nullable()->comment('入庫日期/購買日期')->after('keeper');
            $table->text('remark')->nullable()->comment('備註')->after('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // 復原時刪除這些欄位
            $table->dropColumn([
                'brand', 
                'serial_number', 
                'unit', 
                'department', 
                'keeper', 
                'purchased_at', 
                'remark'
            ]);
        });
    }
};