<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\HouseDog;
use App\Services\PantherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GoFetchListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('high');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function handle(PantherService $pantherService): void
    {
        $start = hrtime(true);

        $data = $pantherService->fetchData(config('services.panther.uris.inHouseList'));
        $columns = collect($data['columns'])->pluck('index', 'filterKey');


        $existingIds = HouseDog::pluck('id')->toArray();
        $newIds = collect($data['rows'])->pluck('id')->toArray();
        $idsToDelete = array_diff($existingIds, $newIds);
        HouseDog::whereIn('id', $idsToDelete)->delete();
        $cabins = Cabin::all();

        foreach ($data['rows'] as $row) {
            $petId = $row[$columns['petId']];
            $cabin = $cabins->where('cabinName', $row[$columns['dateCabin']])->first();

            HouseDog::updateOrCreate(
                ['id' => $petId],
                [
                    'name' => $this->trimToNull($row[$columns['name']]),
                    'gender' => $this->trimToNull($row[$columns['gender']]),
                    'cabin_id' => $cabin ? $cabin->id : null,
                ]
            );
            GoFetchDogJob::dispatch($petId, $pantherService);
        }

        $stop = hrtime(true);
        sleep(4 - (($stop - $start) / 1e9));
    }

    function trimToNull($value) {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }


}
