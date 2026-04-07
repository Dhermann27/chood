<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Icon extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'title', 'class', 'color', 'group_name'];
}
