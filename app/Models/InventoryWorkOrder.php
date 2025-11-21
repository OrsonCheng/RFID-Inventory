<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryWorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'responsible_user_id',
        'auditor_user_id',
        'status',
    ];

    /**
     * 工單對應的保管人
     */
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * 工單對應的盤點人
     */
    public function auditorUser()
    {
        return $this->belongsTo(User::class, 'auditor_user_id');
    }

    /**
     * 工單擁有的所有盤點項目
     */
    public function items()
    {
        return $this->hasMany(InventoryWorkOrderItem::class, 'work_order_id');
    }
}