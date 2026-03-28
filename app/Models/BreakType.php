<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BreakType extends Model
{
    public $timestamps = false;
    protected $fillable = ['label', 'short_label', 'duration_minutes', 'behavior', 'display_order'];
}
