<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class YardAssignment extends Model
{
    protected $fillable = ['wiw_user_id', 'description'];

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'wiw_user_id', 'wiw_user_id');
    }
}
