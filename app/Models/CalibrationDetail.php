<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalibrationDetail extends Model
{
    use HasFactory;

    protected $table = 'calibration_details';

    protected $fillable = [
        'record_id',
        'calibration_type',
        'range_info',
        'test_item',
        'standard_value',
        'meter_value',
        'adc_value',
        'difference',
        'status',
    ];

    /**
     * 所屬校正主紀錄
     */
    public function master()
    {
        return $this->belongsTo(CalibrationMaster::class, 'record_id', 'id');
    }
}
