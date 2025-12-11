<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    /**
     * 可以被批量賦值的屬性 (Mass Assignable)
     */
    protected $fillable = [
        'asset_tag',        // 資產編號
        'rfid_tag',         // RFID
        'ppid',             // PPID [新增]
        
        'name',             // 設備名稱
        'spec',             // 規格說明 [新增]
        'brand',            // 品牌
        'model',            // 型號
        
        'purchased_at',     // 取得日期
        'source_type',      // 來源類型 [新增]
        
        'company',          // 所屬公司 [新增]
        'department_code',  // 部門代碼 [新增]
        'department_name',  // 部門名稱
        
        'factory_area',     // 廠區 [新增]
        'floor',            // 樓層 [新增]
        'area',             // 綫別/區域 [新增]
        'location',         // 站別/儲位
        
        'keeper',           // 保管人
        'status',           // 狀態
        'responsible_user_id',
        'remark'
    ];

    /**
     * 屬性轉型
     */
    protected $casts = [
        'purchased_at' => 'date', // 自動轉為 Carbon 日期物件
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