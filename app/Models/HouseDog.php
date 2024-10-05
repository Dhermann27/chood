<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HouseDog extends Model
{
    use HasFactory;
    protected $fillable = ['id', 'name', 'gender', 'photoUri', 'size', 'cabinName'];
}
