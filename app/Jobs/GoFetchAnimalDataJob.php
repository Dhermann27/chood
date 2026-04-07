<?php

namespace App\Jobs;

use App\Models\Allergy;
use App\Models\Dog;
use App\Models\Feeding;
use App\Models\Medication;
use App\Models\Timeslot;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;

class GoFetchAnimalDataJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $ownerId) {}

    public function uniqueId(): string
    {
        return 'animal_owner' . $this->ownerId;
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $url = config('services.gingr.uris.ownerData') . $this->ownerId;

        try {
            $output = $fetchDataService->fetchWithSession($url);
        } catch (Exception $e) {
            if (in_array($e->getCode(), [404, 500])) {
                Log::warning("GoFetchAnimalDataJob skipping owner {$this->ownerId}: {$e->getMessage()}");
                return;
            }
            Cache::forget('gingr_session_cookies');
            throw $e;
        }

        $timeslots = Timeslot::pluck('id', 'gingr_label');

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

            $feedingSchedule = $animal['feeding_schedule'] ?? null;
            $dog->update([
                'food_type'      => $feedingSchedule['foodType']['label'] ?? null,
                'feeding_method' => $feedingSchedule['feedingMethod']['label'] ?? null,
                'feeding_notes'  => trim($feedingSchedule['feedingNotes'] ?? '') ?: null,
            ]);

            $this->getAllergies($petId, $animal['allergies'] ?? null);
            $this->getMedications($petId, $animal, $timeslots);
            $this->getFeedings($petId, $feedingSchedule, $timeslots);
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

    private function resolveTimeslot(string $label, $timeslots): ?int
    {
        $name = trim(explode(' - ', ltrim($label, '*'))[0]);
        return $timeslots->get($name);
    }

    private function getMedications(string $petId, array $animal, $timeslots): void
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

        // Handling note
        $handlingNote = trim($animal['medication_schedule']['medicationNotes'] ?? '');
        if ($handlingNote !== '') {
            Medication::create([
                'pet_id'      => $petId,
                'type'        => 'Handling',
                'description' => $handlingNote,
            ]);
        }

        // Individual medication schedules
        foreach ($animal['medication_schedule']['medicationSchedules'] ?? [] as $schedule) {
            $timeslotId = $this->resolveTimeslot($schedule['medicationSchedule']['label'] ?? '', $timeslots);

            foreach ($schedule['medications'] ?? [] as $med) {
                Medication::create([
                    'medication_id' => $med['id'],
                    'pet_id'        => $petId,
                    'type_id'       => $med['medicationType']['value'] ?? null,
                    'type'          => $med['medicationType']['label'] ?? '',
                    'timeslot_id'   => $timeslotId,
                    'quantity'      => $med['medicationAmount']['label'] ?? null,
                    'unit'          => $med['medicationUnit']['label'] ?? null,
                    'start_date'    => $med['start_date'] ?: null,
                    'end_date'      => $med['end_date'] ?: null,
                    'description'   => trim($med['medicationNotes'] ?? '') ?: null,
                ]);
            }
        }
    }

    private function getFeedings(string $petId, ?array $feedingSchedule, $timeslots): void
    {
        Feeding::where('pet_id', $petId)->where('is_task', 0)->delete();

        if (!$feedingSchedule) return;

        foreach ($feedingSchedule['feedingSchedules'] ?? [] as $schedule) {
            Feeding::create([
                'feeding_id'  => $schedule['id'],
                'pet_id'      => $petId,
                'timeslot_id' => $this->resolveTimeslot($schedule['feedingSchedule']['label'] ?? '', $timeslots),
                'quantity'    => $schedule['feedingAmount']['label'] ?? null,
                'unit'        => $schedule['feedingUnit']['label'] ?? null,
                'description' => trim($schedule['feedingInstructions'] ?? '') ?: null,
            ]);
        }
    }
}
