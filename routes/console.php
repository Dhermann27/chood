<?php

use App\Jobs\GoFetchListJob;
use App\Jobs\MarkCabinsForCleaning;
use App\Services\NodeService;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new GoFetchListJob(app(NodeService::class)))->everyFifteenSeconds()->between('6:00', '19:30');
Schedule::job(new MarkCabinsForCleaning())->daily();
