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
        Schema::table('calibration_masters', function (Blueprint $table) {
            $table->float('temperature')->nullable()->after('final_result')->comment('校正當時溫度 (°C)');
            $table->float('humidity')->nullable()->after('temperature')->comment('校正當時濕度 (%RH)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_masters', function (Blueprint $table) {
            $table->dropColumn(['temperature', 'humidity']);
        });
    }
};

