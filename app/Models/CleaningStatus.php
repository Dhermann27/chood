<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CleaningStatus extends Model
{
    use HasFactory;

    protected $table = 'cleaning_status';
    protected $fillable = ['cabin_id', 'cleaning_type', 'homebase_user_id', 'completed_at', 'created_by', 'created_at', 'updated_by', 'updated_at'];
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

    public function cabin(): HasOne
    {
        return $this->hasOne(Cabin::class, 'id', 'cabin_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'homebase_user_id', 'homebase_user_id');
    }

}
