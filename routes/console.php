<?php

use App\Jobs\GoFetchListJob;
use App\Jobs\GoFetchServiceListJob;
use App\Jobs\Homebase\GoFetchEmployeesJob;
use App\Jobs\Homebase\GoFetchShiftsJob;
use App\Jobs\Homebase\GoFetchTimecardsJob;
use App\Jobs\MarkCabinsForCleaningJob;
use App\Jobs\SyncDogServicesJob;
use App\Jobs\SyncFutureReservationsJob;
use App\Services\FetchDataService;
use Illuminate\Support\Facades\Schedule;


Schedule::command('telescope:prune --hours=24')->daily();
Schedule::job(new GoFetchListJob(app(FetchDataService::class)))->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new GoFetchTimecardsJob())->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new SyncFutureReservationsJob(app(FetchDataService::class)))->everyMinute()->between('6:00', '19:30');
Schedule::job(new SyncDogServicesJob())->everyMinute()->between('6:00', '19:30');
Schedule::job(new GoFetchServiceListJob(app(FetchDataService::class)))->daily();
Schedule::job(new MarkCabinsForCleaningJob())->daily();
Schedule::job(new GoFetchEmployeesJob())->daily();
Schedule::job(new GoFetchShiftsJob())->daily();
