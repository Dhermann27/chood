<?php

namespace App\Jobs\Report;

use App\Models\Report;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GoFetchReports implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $reportId, public readonly array $cookies)
    {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return $this->reportId;
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        // Ensure the report exists before dispatching sub-jobs
        Report::findOrFail($this->reportId);

        FetchChargesJob::dispatch($this->reportId, $this->cookies);
        FetchPaymentsJob::dispatch($this->reportId, $this->cookies);
        FetchBoardingJob::dispatch($this->reportId, $this->cookies);
    }
}
