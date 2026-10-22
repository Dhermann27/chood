<?php

namespace App\Jobs;

use App\Models\RestBreak;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloseActiveBreaksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        RestBreak::whereNull('ended_at')->with('dog')->get()->each(function (RestBreak $break) {
            $startDay = Carbon::parse($break->started_at)->startOfDay();
            $cutoff = ($break->dog?->is_boarding)
                ? $startDay->copy()->setTime(16, 30)  // boarders: 4:30pm
                : $startDay->copy()->setTime(17, 30); // daycare/other: 5:30pm

            $break->update(['ended_at' => $cutoff]);
        });
    }
}
