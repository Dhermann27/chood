<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeYardRotation extends Model
{
    protected $fillable = ['wiw_user_id', 'yard_id', 'rotation_id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'wiw_user_id', 'wiw_user_id');
    }

    public function yard(): BelongsTo
    {
        return $this->belongsTo(Yard::class);
    }

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class);
    }

}
