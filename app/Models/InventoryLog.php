<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    // 不需要 updated_at，因為日誌是唯讀的歷史紀錄
    public $timestamps = false;

    protected $fillable = [
        'inventory_work_order_id',
        'fixed_asset_id',
        'user_id',
        'action',
        'note'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
	
	public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }
}