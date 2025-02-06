<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['username', 'report_date', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

}
