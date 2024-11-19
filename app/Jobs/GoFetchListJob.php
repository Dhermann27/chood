<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\Dog;
use App\Models\DogService;
use App\Models\Service;
use App\Services\PantherService;
use Carbon\Carbon;
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


        $existingIds = Dog::pluck('id')->toArray();
        $newIds = collect($data['rows'])->pluck(1)->toArray();
        $idsToDelete = array_diff($existingIds, $newIds);
        Dog::whereIn('id', $idsToDelete)->delete();

        $cabins = Cabin::all();
        $services = Service::all()->pluck('id', 'code');

        foreach ($data['rows'] as $row) {
            $petId = $row[$columns['petId']];
            $cabin = $cabins->where('cabinName', $row[$columns['dateCabin']])->first();

            Dog::updateOrCreate(
                ['id' => $petId],
                [
                    'name' => $this->trimToNull($row[$columns['name']]),
                    'gender' => $this->trimToNull($row[$columns['gender']]),
                    'cabin_id' => $cabin ? $cabin->id : null,
                    'checkout' => Carbon::createFromFormat('m/d/Y g:i A', $row[$columns['checkOutDate']] . " " . $row[$columns['checkOutTime']])
                ]
            );

            DogService::where('dog_id', $petId)->get();
            $existingIds = DogService::where('dog_id', $petId)->get()->pluck('service_id')->toArray();
            $newIds = explode(', ', $row[$columns['serviceCode']]);
            $idsToDelete = array_diff($existingIds, $newIds);
            DogService::where('dog_id', $petId)->whereIn('service_id', $idsToDelete)->delete();

            foreach ($newIds as $serviceId) {
                if ($services->has($serviceId)) {
                    DogService::updateOrCreate(
                        ['dog_id' => $petId, 'service_id' => $services[$serviceId]],
                        ['dog_id' => $petId, 'service_id' => $services[$serviceId]]
                    );
                }
            }

            GoFetchDogJob::dispatch($petId, $pantherService);
        }

        $stop = hrtime(true);
        sleep(max(4 - (($stop - $start) / 1e9), 0));
    }

    function trimToNull($value)
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }


}
