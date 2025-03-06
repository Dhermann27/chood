<?php

use App\Jobs\GoFetchHomebaseEmployeesJob;
use App\Jobs\GoFetchHomebaseShiftsJob;
use App\Jobs\GoFetchHomebaseTimecardsJob;
use App\Jobs\GoFetchListJob;
use App\Jobs\MarkCabinsForCleaningJob;
use App\Services\FetchDataService;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new GoFetchListJob(app(FetchDataService::class)))->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new GoFetchHomebaseTimecardsJob())->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new MarkCabinsForCleaningJob())->daily();
Schedule::job(new GoFetchHomebaseEmployeesJob())->daily();
Schedule::job(new GoFetchHomebaseShiftsJob())->daily();
