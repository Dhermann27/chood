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

/**
 *
 */
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
        if (count($idsToDelete) > 0) {
            Dog::whereIn('pet_id', $idsToDelete)->delete();
        }

        $cabins = Cabin::all()->pluck('id', 'cabinName');
        $services = Service::all()->pluck('id', 'code');

        foreach ($data['rows'] as $row) {
            $updateValues = $this->getFilteredValues($row, $columns, $cabins);

            $dog = Dog::whereRaw('MATCH(firstname) AGAINST (? IN BOOLEAN MODE) AND MATCH(lastname) AGAINST (? IN BOOLEAN MODE)',
                [$updateValues['firstname'], $updateValues['lastname']]);
            $dog = $dog->first();
            if ($dog) {
                $dog->update(array_merge($updateValues, ['pet_id' => $row[$columns['petId']]]));
            } else {
                $dog = Dog::updateOrCreate(['pet_id' => $row[$columns['petId']]], $updateValues);
            }

            $existingIds = DogService::where('dog_id', $dog->id)->pluck('service_id')->toArray();
            $newCodes = explode(',', $row[$columns['serviceCode']]);
            $newIds = [];
            foreach ($newCodes as $code) {
                if ($services->has($code)) {
                    $newIds[] = $services[$code]; // Map code to ID
                }
            }
            $idsToDelete = array_diff($existingIds, $newIds);
            if (count($idsToDelete) > 0) {
                DogService::where('dog_id', $dog->id)->whereIn('service_id', $idsToDelete)->delete();
            }

            foreach ($newIds as $serviceId) {
                DogService::updateOrCreate(['dog_id' => $dog->id, 'service_id' => $serviceId]);
            }

            GoFetchDogJob::dispatch($dog->pet_id, $nodeController);
        }
    }

    private function getFilteredValues($row, $columns, $cabins): array
    {
        $cabinId = $cabins->has($row[$columns['dateCabin']]) ? $cabins[$row[$columns['dateCabin']]] : null;
        $updateValues = [
            'firstname' => $this->trimToNull($row[$columns['name']]),
            'lastname' => $this->trimToNull($row[$columns['lastName']]),
            'gender' => $this->trimToNull($row[$columns['gender']]),
            'cabin_id' => $cabinId,
            'is_inhouse' => $cabinId ? 1 : 0,
            'checkout' => Carbon::createFromFormat('m/d/Y g:i A', $row[$columns['checkOutDate']] . " " . $row[$columns['checkOutTime']])
        ];
        return array_filter($updateValues, function ($value) {
            return !is_null($value);
        });
    }

    private function trimToNull($value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }


}
