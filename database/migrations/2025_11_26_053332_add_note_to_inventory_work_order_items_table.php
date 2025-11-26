<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
	{
		Schema::table('inventory_work_order_items', function (Blueprint $table) {
			// 在 status 欄位後面新增 note，允許為空 (nullable)
			$table->string('note')->nullable()->after('status')->comment('盤虧原因或其他備註');
		});
	}

	public function down(): void
	{
		Schema::table('inventory_work_order_items', function (Blueprint $table) {
			$table->dropColumn('note');
		});
	}
};
