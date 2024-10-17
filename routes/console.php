<?php

use App\Jobs\GoFetchListJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new GoFetchListJob())->everyFifteenSeconds();//->between('6:00', '19:30');
