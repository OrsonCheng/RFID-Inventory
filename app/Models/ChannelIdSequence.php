<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChannelIdSequence extends Model
{
    protected $primaryKey = 'prefix_type';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['prefix_type', 'current_seq'];
}
