<?php

use App\Jobs\GoFetchListJob;
use App\Jobs\GoFetchServiceListJob;
use App\Jobs\WIW\GoFetchEmployeesJob;
use App\Jobs\WIW\GoFetchShiftsJob;
use App\Jobs\MarkCabinsForCleaningJob;
use App\Models\Dog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('telescope:prune --hours=24')->daily();
Schedule::call(fn() => Dog::whereNotNull('checked_out_at')->where('checked_out_at', '<', now()->subDay())->delete())->daily();
Schedule::job(new GoFetchListJob())->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new GoFetchServiceListJob())->daily();
Schedule::job(new MarkCabinsForCleaningJob())->daily();
Schedule::job(new GoFetchEmployeesJob())->daily();
Schedule::job(new GoFetchShiftsJob())->daily();
