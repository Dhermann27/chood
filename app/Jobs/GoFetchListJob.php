<?php

namespace App\Jobs;

use App\Models\Allergy;
use App\Models\Cabin;
use App\Models\Dog;
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
use Illuminate\Support\Facades\Log;

/**
 *
 */
class GoFetchListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        if (!isset($output['data']) || !is_array($output['data'])) {
            Log::warning('GoFetchListJob returned no usable data.', [
                'output_type' => gettype($output),
                'output_preview' => is_array($output) ? array_slice($output, 0, 5) : $output,
            ]);
            throw new Exception('No usable data found in response.');
        }

        $data = $output['data'][0];
        $columns = collect($data['columns'])->pluck('index', 'filterKey');
        $cabins = Cabin::all()->keyBy(fn($c) => $this->normCabinName($c->cabinName))->map(fn($c) => $c->id);
        $serviceMap = Service::pluck('id', 'code');

        $activePetIds = [];
        foreach ($data['rows'] as $row) {
            $updateValues = $this->getFilteredValues($row, $columns, $cabins);
            $dog = Dog::updateOrCreate(['pet_id' => $row[$columns['petId']]], $updateValues);
            $activePetIds[] = $dog->pet_id;

            GoFetchBookingJob::dispatch($row[$columns['bookingId']], $row[$columns['orderId']]);

//            GoFetchDogJob::dispatch($dog->pet_id); Order contains photo and nickname
            if ($row[$columns['feedingAttributeCount']] > 0) {
                GoFetchFeedingJob::dispatch($dog->pet_id, $dog->account_id);
            } else {
                Feeding::where('pet_id', $dog->pet_id)->delete();
            }
            if ($row[$columns['medicationAttributeCount']] > 0 ||
                $row[$columns['medicalConditionsAttributeCount']] > 0) {
                GoFetchMedicationJob::dispatch($dog->pet_id, $dog->account_id);
            } else {
                Medication::where('pet_id', $dog->pet_id)->delete();
            }
            if ($row[$columns['allergiesAttributeCount']] > 0) {
                GoFetchAllergyJob::dispatch($dog->pet_id, $dog->account_id);
            } else {
                Allergy::where('pet_id', $dog->pet_id)->delete();
            }

        }

        $inactiveDogs = Dog::whereNotIn('pet_id', $activePetIds)->get();
        Dog::whereIn('id', $inactiveDogs->pluck('id'))->delete();

        $delay = config('services.dd.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);

    }

    private function getFilteredValues(array $row, Collection $columns, Collection $cabins): array
    {
        $cabinId = $cabins->get($this->normCabinName($row[$columns['dateCabin']]), null);
        $updateValues = [
            'order_id' => $row[$columns['orderId']],
            'account_id' => $this->trimToNull($row[$columns['accountId']]),
            'firstname' => $this->trimToNull($row[$columns['name']]),
            'lastname' => $this->trimToNull($row[$columns['lastName']]),
            'gender' => $this->trimToNull($row[$columns['gender']]),
            'weight' => $this->trimToNull(intval($row[$columns['weight']])),
            'cabin_id' => $cabinId,
            'housing_code' => $this->trimToNull(explode(',', $row[$columns['serviceCode']])[0]),
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

    function normCabinName(string $key): string
    {
        // Remove all spaces, dashes, hyphens, em-dashes, etc., and lowercase
        return strtolower(preg_replace('/[\s\-—–]+/', '', $key));
    }

}
