<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleaningStatus extends Model
{
    use HasFactory;

    protected $table = 'cleaning_status';
    protected $fillable = ['cabin_id', 'cleaning_type'];
    protected $primaryKey = 'cabin_id';
    public $incrementing = false;
    public $timestamps = false;

    const STATUS_DAILY = 'daily';
    const STATUS_DEEP = 'deep';

    public static function getStatusOptions()
    {
        return [
            self::STATUS_DAILY,
            self::STATUS_DEEP
        ];
    }

}
