<?php

namespace App\Jobs;

use App\Models\Allergy;
use App\Models\Dog;
use App\Models\Feeding;
use App\Models\Medication;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;

class GoFetchOwnerDataJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $ownerId) {}

    public function uniqueId(): string
    {
        return 'owner' . $this->ownerId;
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $url = config('services.gingr.uris.ownerData') . $this->ownerId;
        $output = $fetchDataService->fetchWithSession($url);

        if (empty($output['animals'])) {
            return;
        }

        foreach ($output['animals'] as $animal) {
            $petId = $animal['system_id'];
            $dog = Dog::where('pet_id', $petId)->first();
            if (!$dog) continue;

            $dog->update([
                'gender' => match($animal['gender'] ?? null) {
                    'Male'   => 'M',
                    'Female' => 'F',
                    default  => null,
                },
            ]);

            $this->getAllergies($petId, $animal['allergies'] ?? null);
            $this->getMedications($petId, $animal);
            $this->getFeedings($petId, $animal['feeding_schedule'] ?? null);
        }

        $delay = config('services.gingr.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }

    private function getAllergies(string $petId, ?string $allergiesHtml): void
    {
        Allergy::where('pet_id', $petId)->delete();

        $text = $allergiesHtml ? trim(strip_tags($allergiesHtml)) : '';
        if ($text !== '') {
            Allergy::create(['pet_id' => $petId, 'description' => $text]);
        }
    }

    private function getMedications(string $petId, array $animal): void
    {
        Medication::where('pet_id', $petId)->delete();

        // Predefined medical condition
        $condition = trim($animal['medical_condition'] ?? '');
        if ($condition !== '') {
            Medication::create([
                'pet_id'      => $petId,
                'type'        => 'Medical Condition',
                'description' => $condition,
            ]);
        }

        // Free-text other condition
        $otherCondition = trim($animal['medical_conditions_please_explain'] ?? '');
        if ($otherCondition !== '') {
            Medication::create([
                'pet_id'      => $petId,
                'type'        => 'Medical Condition',
                'description' => $otherCondition,
            ]);
        }

        // Actual medication schedule (Rx)
        foreach ($animal['medication_schedule']['medicationSchedules'] ?? [] as $schedule) {
            $scheduleLabel = $schedule['medicationSchedule']['label'] ?? '';
            foreach ($schedule['medications'] ?? [] as $med) {
                $amount = $med['medicationAmount']['label'] ?? '';
                $unit   = $med['medicationUnit']['label'] ?? '';
                $notes  = trim($med['medicationNotes'] ?? '');

                $description = trim(implode(' ', array_filter([
                    $scheduleLabel,
                    $amount && $unit ? "$amount $unit" : null,
                    $notes ?: null,
                ])));

                Medication::create([
                    'medication_id' => $med['id'],
                    'pet_id'        => $petId,
                    'type_id'       => $med['medicationType']['value'] ?? null,
                    'type'          => $med['medicationType']['label'] ?? '',
                    'description'   => $description ?: null,
                ]);
            }
        }
    }

    private function getFeedings(string $petId, ?array $feedingSchedule): void
    {
        Feeding::where('pet_id', $petId)->where('is_task', 0)->delete();

        if (!$feedingSchedule) return;

        $foodType     = $feedingSchedule['foodType']['label'] ?? 'Food';
        $feedingNotes = trim($feedingSchedule['feedingNotes'] ?? '');

        if ($feedingNotes !== '') {
            Feeding::create([
                'pet_id'      => $petId,
                'type'        => $foodType,
                'description' => $feedingNotes,
            ]);
        }

        foreach ($feedingSchedule['feedingSchedules'] ?? [] as $schedule) {
            $scheduleLabel = $schedule['feedingSchedule']['label'] ?? '';
            $instructions  = trim($schedule['feedingInstructions'] ?? '');
            $amount        = $schedule['feedingAmount']['label'] ?? '';
            $unit          = $schedule['feedingUnit']['label'] ?? '';

            $description = trim(implode(' ', array_filter([
                $scheduleLabel,
                $instructions ?: null,
                $amount && $unit ? "($amount $unit)" : null,
            ])));

            Feeding::create([
                'feeding_id'  => $schedule['id'],
                'pet_id'      => $petId,
                'type'        => $foodType,
                'description' => $description ?: null,
            ]);
        }
    }
}
