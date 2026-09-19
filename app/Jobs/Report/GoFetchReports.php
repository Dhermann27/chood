<?php

namespace App\Jobs\Report;

use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GoFetchReports implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function __construct(public readonly string $reportId, public readonly array $cookies)
    {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return $this->reportId;
    }

    public function handle(): void
    {
        // Ensure the report exists before dispatching sub-jobs
        Report::findOrFail($this->reportId);

        FetchChargesJob::dispatch($this->reportId, $this->cookies);
        FetchBoardingJob::dispatch($this->reportId, $this->cookies);
        FetchOccupancyJob::dispatch($this->reportId, $this->cookies);
    }
}
