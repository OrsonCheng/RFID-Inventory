<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag',
        'rfid_tag',
        'name',
        'model',
        'status',
        'responsible_user_id',
        'brand',
        'location',
        'supplier',
        'purchase_date',
        'price',
    ];

    /**
     * 一個固定資產只屬於一個保管人 (User)
     */
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * 一個固定資產可能出現在多張工單的明細中
     */
    public function workOrderItems()
    {
        return $this->hasMany(InventoryWorkOrderItem::class);
    }
}