<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DogService extends Model
{
    protected $table = 'dog_service';
    protected $fillable = ['dog_id', 'service_id', 'completed'];

    public function dog()
    {
        return $this->hasOne(Dog::class, 'id', 'dog_id');
    }
    public function service()
    {
        return $this->hasOne(Service::class, 'id', 'service_id');
    }

}
