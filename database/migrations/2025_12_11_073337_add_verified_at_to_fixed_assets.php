<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
	{
		Schema::table('fixed_assets', function (Blueprint $table) {
			// 新增一個可為空的時間欄位
			$table->timestamp('verified_at')->nullable()->after('remark')->comment('最近一次驗證通過的時間');
		});
	}

	public function down()
	{
		Schema::table('fixed_assets', function (Blueprint $table) {
			$table->dropColumn('verified_at');
		});
	}
};
