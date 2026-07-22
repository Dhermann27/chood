<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timeslot extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'gingr_label', 'display_order'];
    const int LUNCH = 1001;
}
