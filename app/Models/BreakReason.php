<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BreakReason extends Model
{
    public $timestamps = false;
    protected $fillable = ['label', 'icon', 'display_order'];

    public function restBreaks(): BelongsToMany
    {
        return $this->belongsToMany(RestBreak::class, 'rest_break_reasons');
    }
}
