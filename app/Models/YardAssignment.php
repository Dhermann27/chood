<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class YardAssignment extends Model
{
    protected $fillable = ['homebase_user_id', 'description'];

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'homebase_user_id', 'homebase_user_id');
    }
}
