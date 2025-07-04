<?php

namespace App\Console\Commands;

use App\Jobs\GoFetchServiceListJob;
use App\Jobs\Homebase\GoFetchEmployeesJob;
use App\Jobs\Homebase\GoFetchShiftsJob;
use App\Services\FetchDataService;
use Illuminate\Console\Command;

class DeploymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:chood-deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initial deployment run of daily() (i.e. midnight) jobs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        GoFetchServiceListJob::dispatchSync(new FetchDataService());
        GoFetchEmployeesJob::dispatchSync();
        GoFetchShiftsJob::dispatchSync();

    }
}
