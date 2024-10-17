<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dog extends Model
{
    use HasFactory;
    
    protected $fillable = ['id', 'name', 'gender', 'photoUri', 'size', 'cabin_id'];

    public function cabin() {
        return $this->hasOne(Cabin::class, 'id', 'cabin_id');
    }


    public function services() {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
