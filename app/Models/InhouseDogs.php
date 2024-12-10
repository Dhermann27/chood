<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InhouseDogs extends Model
{
    protected $casts = [
        'checkout' => 'datetime'
    ];

    public function cabin(): HasOne
    {
        return $this->hasOne(Cabin::class);
    }


    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
