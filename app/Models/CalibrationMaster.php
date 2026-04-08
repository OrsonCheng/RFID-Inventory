<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalibrationMaster extends Model
{
    use HasFactory;

    protected $table = 'calibration_masters';

    // UUID 為主鍵設定
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'channel_id',
        'asset_number',
        'channel_ip',
        'employee_id',
        'start_time',
        'end_time',
        'next_calibration_date',
        'calibration_csv_md5',
        'final_result',
    ];

    /**
     * 校正量測明細 (關聯)
     */
    public function details()
    {
        return $this->hasMany(CalibrationDetail::class, 'record_id', 'id');
    }

    /**
     * 前置點檢明細 (關聯)
     */
    public function preVerifyDetails()
    {
        return $this->hasMany(PreVerifyDetail::class, 'record_id', 'id');
    }
}
