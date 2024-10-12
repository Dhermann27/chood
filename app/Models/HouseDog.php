<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HouseDog extends Model
{
    use HasFactory;
    protected $fillable = ['id', 'name', 'gender', 'photoUri', 'size', 'cabin_id'];

    public function cabin() {
        return $this->hasOne(Cabin::class, 'id', 'cabin_id');
    }
}
