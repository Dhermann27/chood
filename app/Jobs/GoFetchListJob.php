<?php

namespace App\Jobs;

use App\Http\Controllers\NodeController;
use App\Models\Cabin;
use App\Models\Dog;
use App\Models\DogService;
use App\Models\Service;
use Carbon\Carbon;
use Exception;
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
     * @throws Exception
     */
    public function handle(NodeController $nodeController): void
    {
        $output = $nodeController->fetchData(config('services.puppeteer.uris.inHouseList'))->getData(true);
        if (!isset($output['data']) || !is_array($output['data']) || count($output['data']) == 0) {
            throw new Exception("No data found: " . $output);
        }
        $data = $output['data'][0];
        $columns = collect($data['columns'])->pluck('index', 'filterKey');


        $existingIds = Dog::pluck('pet_id')->toArray();
        $newIds = collect($data['rows'])->pluck(1)->toArray();
        $idsToDelete = array_diff($existingIds, $newIds);
        Dog::whereIn('pet_id', $idsToDelete)->delete();

        $cabins = Cabin::all();
        $services = Service::all()->pluck('id', 'code');

        foreach ($data['rows'] as $row) {
            $petId = $row[$columns['petId']];
            $cabin = $cabins->where('cabinName', $row[$columns['dateCabin']])->first();

            $updateValues = [
                'name' => $this->trimToNull($row[$columns['name']]),
                'gender' => $this->trimToNull($row[$columns['gender']]),
                'cabin_id' => $cabin ? $cabin->id : null,
                'is_inhouse' => $cabin ? 1 : 0,
                'checkout' => Carbon::createFromFormat('m/d/Y g:i A', $row[$columns['checkOutDate']] . " " . $row[$columns['checkOutTime']])
            ];
            $filteredValues = array_filter($updateValues, function ($value) {
                return !is_null($value);
            });

            $dog = Dog::updateOrCreate(['pet_id' => $petId], $filteredValues);

            foreach (explode(',', $row[$columns['serviceCode']]) as $serviceId) {
                $serviceId = trim($serviceId);
                if ($services->has($serviceId)) {
                    DogService::updateOrCreate(['dog_id' => $dog->id, 'service_id' => $services[$serviceId]]);
                }
            }

            GoFetchDogJob::dispatch($petId, $nodeController);
        }
    }

    function trimToNull($value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }


}
