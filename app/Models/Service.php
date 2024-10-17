<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public $timestamps = false;

    public function dogs() {
        return $this->belongsToMany(Dog::class, 'dog_service', 'service_id', 'dog_id');
    }
}