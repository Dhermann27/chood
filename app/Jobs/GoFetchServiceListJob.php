<?php

namespace App\Jobs;

use App\Services\FetchDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 *
 */
class GoFetchServiceListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    /**
     * TODO: Rewrite for Gingr — need to explore Gingr service list endpoint first.
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        // Not yet implemented for Gingr
    }

}
