<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryWorkOrderItem extends Model
{
    use HasFactory;

    public $timestamps = false; // 明細表通常不需要 created_at/updated_at

    protected $fillable = [
        'work_order_id',
        'fixed_asset_id',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    /**
     * 項目屬於的工單
     */
    public function workOrder()
    {
        return $this->belongsTo(InventoryWorkOrder::class, 'work_order_id');
    }

    /**
     * 項目對應的資產
     */
    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}