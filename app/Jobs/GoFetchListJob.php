<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\Dog;
use App\Models\DogService;
use App\Models\Service;
use App\Services\FetchDataService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 *
 */
class GoFetchListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const BRD = 'BRD'; // All Boarding service codes contain this string
    protected FetchDataService $fetchDataService;

    /**
     * Create a new job instance.
     */
    public function __construct(FetchDataService $fetchDataService)
    {
        $this->fetchDataService = $fetchDataService;
        $this->onQueue('high');
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $payload = [
            "username" => config('services.dd.username'),
            "password" => config('services.dd.password'),
        ];
        $output = $this->fetchDataService->fetchData(config('services.dd.uris.inHouseList'), $payload)
            ->getData(true);
        if (!isset($output['data']) || !is_array($output['data']) || count($output['data']) == 0) {
            throw new Exception("No data found: " . $output);
        }
        $data = $output['data'][0];
        $columns = collect($data['columns'])->pluck('index', 'filterKey');

        // Delete removed dogs
        $newIds = collect($data['rows'])->pluck(1)->toArray();
        Dog::whereNotIn('pet_id', $newIds)->whereNotNull('pet_id')->delete();

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

            GoFetchDogJob::dispatch($dog->pet_id);
            if ($row[$columns['feedingAttributeCount']] > 0) GoFetchFeedingJob::dispatch($dog->pet_id, $dog->accountId);
        }
    }

    private function getFilteredValues(array $row, Collection $columns, Collection $cabins): array
    {
        $cabinId = $cabins->has($row[$columns['dateCabin']]) ? $cabins[$row[$columns['dateCabin']]] : null;
        $updateValues = [
            'accountId' => $this->trimToNull($row[$columns['accountId']]),
            'firstname' => $this->trimToNull($row[$columns['name']]),
            'lastname' => $this->trimToNull($row[$columns['lastName']]),
            'gender' => $this->trimToNull($row[$columns['gender']]),
            'weight' => $this->trimToNull(intval($row[$columns['weight']])),
            'cabin_id' => $cabinId,
            'is_inhouse' => str_contains($row[$columns['serviceCode']], self::BRD) ? 1 : 0,
            'checkin' => Carbon::createFromFormat('m/d/y g:i A', $row[$columns['checkInDate']] . " " . $row[$columns['checkInTime']]),
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
