<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 表 1: 通道 ID 流水號管控表 (解決多台電腦併發取號問題)
        Schema::create('channel_id_sequences', function (Blueprint $table) {
            $table->string('prefix_type', 2)->primary()->comment('機型前綴 (如 A)');
            $table->integer('current_seq')->default(0)->comment('目前最大流水號');
            $table->timestamps();
        });

        DB::table('channel_id_sequences')->insert([
            ['prefix_type' => 'A', 'current_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['prefix_type' => 'B', 'current_seq' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 表 2: 校驗主紀錄 (由 Redis Consumer 寫入)
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

        // 表 3: 校正量測明細 (客戶稽核，由 Redis Consumer 寫入)
        Schema::create('calibration_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_id')->comment('FK → calibration_masters.id');
            $table->string('calibration_type', 50)->nullable()->comment('Charge / Discharge');
            $table->string('range_info', 50)->nullable()->comment('如 12A');
            $table->string('test_item', 50);
            $table->float('standard_value');
            $table->float('read_value');
            $table->float('difference');
            $table->string('status', 10)->comment('PASS / FAIL');
            $table->timestamps();

            $table->foreign('record_id')->references('id')->on('calibration_masters')->onDelete('cascade');
        });

        // 表 4: 前置點檢明細 (內部偏移量分析，由 Redis Consumer 寫入)
        Schema::create('pre_verify_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_id')->comment('FK → calibration_masters.id');
            $table->string('calibration_type', 50)->nullable();
            $table->string('range_info', 50)->nullable();
            $table->string('test_item', 50);
            $table->float('standard_value');
            $table->float('read_value');
            $table->float('difference');
            $table->string('status', 10);
            $table->timestamps();

            $table->foreign('record_id')->references('id')->on('calibration_masters')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pre_verify_details');
        Schema::dropIfExists('calibration_details');
        Schema::dropIfExists('calibration_masters');
        Schema::dropIfExists('channel_id_sequences');
    }
};
