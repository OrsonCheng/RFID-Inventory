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
        Schema::dropIfExists('pre_verify_details');
        Schema::dropIfExists('calibration_details');
        Schema::dropIfExists('calibration_masters');
		
		 // 表 1: 校驗主紀錄 (由 Redis Consumer 寫入)
        Schema::create('calibration_masters', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('RecordId (C# 產生的 GUID)');
            $table->string('channel_id', 10)->comment('通道 ID，如 A0013');
            $table->string('asset_number', 50)->comment('機台資產編號');
            $table->string('channel_ip', 20)->nullable();
            $table->string('employee_id', 50)->comment('校驗人員工號');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('next_calibration_date', 6)->nullable()->comment('yyMMdd');
            $table->string('calibration_csv_md5', 16)->nullable()->comment('校正 CSV 的 MD5 後 16 bytes');
            $table->string('final_result', 10)->comment('PASS / FAIL');
            $table->timestamps();
        });

        // 表 2: 校正量測明細 (客戶稽核，由 Redis Consumer 寫入)
        Schema::create('calibration_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_id')->comment('FK → calibration_masters.id');
            $table->string('calibration_type', 50)->nullable()->comment('Charge / Discharge');
            $table->string('range_info', 50)->nullable()->comment('如 12A');
            $table->string('test_item', 50);
            $table->float('standard_value');
            $table->float('meter_value');
			$table->float('adc_value');
            $table->float('difference');
            $table->string('status', 10)->comment('PASS / FAIL');
            $table->timestamps();

            $table->foreign('record_id')->references('id')->on('calibration_masters')->onDelete('cascade');
        });

        // 表 3: 前置點檢明細 (內部偏移量分析，由 Redis Consumer 寫入)
        Schema::create('pre_verify_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_id')->comment('FK → calibration_masters.id');
            $table->string('calibration_type', 50)->nullable();
            $table->string('range_info', 50)->nullable();
            $table->string('test_item', 50);
            $table->float('standard_value');
            $table->float('meter_value');
			$table->float('adc_value');
            $table->float('difference');
            $table->string('status', 10);
            $table->timestamps();

            $table->foreign('record_id')->references('id')->on('calibration_masters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
