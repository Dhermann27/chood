<?php

namespace App\Jobs;

use App\Models\Allergy;
use App\Models\Cabin;
use App\Models\Dog;
use App\Models\DogService;
use App\Models\Feeding;
use App\Models\Medication;
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

    public function shouldDispatch(): bool
    {
        return !app()->isDownForMaintenance();
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
        if (!isset($output['data']) || !is_array($output['data'])) { // Empty list depressing, but not an error
            throw new Exception("No data found: " . $output);
        }

        if (count($output['data']) > 0) {
            $data = $output['data'][0];
            $columns = collect($data['columns'])->pluck('index', 'filterKey');

            // Delete removed dogs
            $serviceIds = collect($data['rows'])->pluck(1)->toArray();
            Dog::whereNotIn('pet_id', $serviceIds)->whereNotNull('pet_id')->delete();

            $cabins = Cabin::all()->pluck('id', 'cabinName');
            $services = Service::all();
            $serviceMap = $services->pluck('id', 'code');
            $specialServiceIds = $services->whereIn('category', config('services.dd.special_service_cats'))
                ->pluck('id')->toArray();

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

                GoFetchDogJob::dispatch($dog->pet_id);
                if ($row[$columns['feedingAttributeCount']] > 0) {
                    GoFetchFeedingJob::dispatch($dog->pet_id, $dog->accountId);
                } else {
                    Feeding::where('pet_id', $dog->pet_id)->delete();
                }
                if ($row[$columns['medicationAttributeCount']] > 0 ||
                    $row[$columns['medicalConditionsAttributeCount']] > 0) {
                    GoFetchMedicationJob::dispatch($dog->pet_id, $dog->accountId);
                } else {
                    Medication::where('pet_id', $dog->pet_id)->delete();
                }
                if ($row[$columns['allergiesAttributeCount']] > 0) {
                    GoFetchAllergyJob::dispatch($dog->pet_id, $dog->accountId);
                } else {
                    Allergy::where('pet_id', $dog->pet_id)->delete();
                }


                $serviceCodes = array_map('trim', explode(',', $row[$columns['serviceCode']]));
                $serviceIds = [];
                foreach ($serviceCodes as $code) {
                    if ($serviceMap->has($code)) {
                        $serviceIds[] = $serviceMap[$code]; // Map code to ID
                    } else {
                        throw new Exception('Unknown service code: ' . $code);
                    }
                }
                DogService::where('pet_id', $dog->pet_id)->whereNotIn('service_id', $serviceIds)->delete();

                foreach ($serviceIds as $serviceId) {
                    DogService::firstOrCreate(['pet_id' => $dog->pet_id, 'service_id' => $serviceId]);
                }
                if (array_intersect($serviceIds, $specialServiceIds)) {
                    GoFetchBookingJob::dispatch($row[$columns['bookingId']]);
                }

            }
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
