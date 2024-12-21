<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['pet_id', 'name', 'gender', 'photoUri', 'size', 'cabin_id', 'is_inhouse', 'checkout'];

    protected $casts = [
        'checkout' => 'datetime'
    ];

    public function cabin() : HasOne {
        return $this->hasOne(Cabin::class);
    }


    public function services() : BelongsToMany {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
