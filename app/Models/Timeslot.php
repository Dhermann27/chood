<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timeslot extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'display_order'];
}
