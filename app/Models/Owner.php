<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    protected $fillable = [
        'gingr_owner_id', 'first_name', 'last_name',
        'cell_phone', 'emergency_contact_name', 'emergency_contact_phone',
    ];

    public function dogs(): HasMany
    {
        return $this->hasMany(Dog::class);
    }
}
