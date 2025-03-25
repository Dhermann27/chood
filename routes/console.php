<?php

use App\Jobs\GoFetchListJob;
use App\Jobs\Homebase\GoFetchEmployeesJob;
use App\Jobs\Homebase\GoFetchShiftsJob;
use App\Jobs\Homebase\GoFetchTimecardsJob;
use App\Jobs\MarkCabinsForCleaningJob;
use App\Services\FetchDataService;
use Illuminate\Support\Facades\Schedule;


Schedule::command('telescope:prune --hours=24')->daily();
Schedule::job(new GoFetchListJob(app(FetchDataService::class)))->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new GoFetchTimecardsJob())->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new MarkCabinsForCleaningJob())->daily();
Schedule::job(new GoFetchEmployeesJob())->daily();
Schedule::job(new GoFetchShiftsJob())->daily();
