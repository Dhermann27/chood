<?php

namespace App\Jobs\Report;

use App\Models\Breed;
use App\Models\Report;
use App\Services\FetchDataService;
use App\Traits\ParsesAnimalNotes;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchOwnerCardJob implements ShouldQueue
{
    use Queueable, ParsesAnimalNotes;

    public function __construct(
        public readonly string $reportId,
        public readonly string $ownerId,
        public readonly array  $reservations,
    )
    {
        $this->onQueue('high');
    }

    public function handle(FetchDataService $fetchDataService): void
    {
        $breeds = Breed::pluck('name', 'gingr_breed_id');

        try {
            $ownerData = $fetchDataService->fetchWithSession(
                config('services.gingr.uris.ownerData') . $this->ownerId
            );
        } catch (Exception $e) {
            Log::warning("FetchOwnerCardJob: could not fetch owner {$this->ownerId}: {$e->getMessage()}");
            $this->mergeAndMaybeFinalize([]);
            return;
        }

        $newCards = [];
        foreach ($this->reservations as $res) {
            $card = $this->cardFromOwnerData($ownerData, $res['pet_id'], $res, $breeds);
            if ($card) $newCards[] = $card;
        }

        $this->mergeAndMaybeFinalize($newCards);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("FetchOwnerCardJob failed for owner {$this->ownerId}", ['error' => $e->getMessage()]);
        $this->mergeAndMaybeFinalize([]);
    }

    private function mergeAndMaybeFinalize(array $newCards): void
    {
        DB::transaction(function () use ($newCards) {
            $report = Report::lockForUpdate()->findOrFail($this->reportId);
            $data = $report->data ?? [];

            $cards = array_merge($data['cards'] ?? [], $newCards);
            $pending = max(0, ($data['pending_owners'] ?? 1) - 1);

            $data['cards'] = $pending === 0 ? FetchCheckinCardsJob::finalize($cards) : $cards;
            $data['pending_owners'] = $pending;
            $data['complete'] = $pending === 0;

            $report->data = $data;
            $report->updated_at = now();
            $report->save();
        });
    }

    private function cardFromOwnerData(array $output, string $petId, array $res, $breeds): array
    {
        $ownerRow = $output['owner'] ?? [];
        $animal = collect($output['animals'] ?? [])->firstWhere('system_id', $petId);

        if (!$animal) return [];

        $feeding = $animal['feeding_schedule'] ?? [];
        $feedings = [];
        foreach ($feeding['feedingSchedules'] ?? [] as $schedule) {
            $label = $schedule['feedingSchedule']['label'] ?? '';
            if (str_contains($label, 'Owner Provided') || str_starts_with($label, 'OP -')) continue;
            $label = preg_replace('/^Lunch\s*-\s*.+/i', 'Lunch', $label);
            $qty = $schedule['feedingAmount']['label'] ?? '';
            $unit = $schedule['feedingUnit']['label'] ?? '';
            $instructions = $this->cleanHtml($schedule['feedingInstructions'] ?? '');
            $line = trim("$label: $qty $unit");
            if ($instructions) $line .= ': ' . $instructions;
            $feedings[] = $line;
        }

        $medSchedule = $animal['medication_schedule'] ?? [];
        $medications = [];
        $handlingNote = trim($medSchedule['medicationNotes'] ?? '');
        if ($handlingNote && !$this->isBoilerplate($handlingNote)) {
            $medications[] = $handlingNote;
        }
        foreach ($medSchedule['medicationSchedules'] ?? [] as $schedule) {
            $slotLabel = trim(explode(' - ', ltrim($schedule['medicationSchedule']['label'] ?? '', '*'))[0]);
            foreach ($schedule['medications'] ?? [] as $med) {
                $type = $med['medicationType']['label'] ?? '';
                $qty = $med['medicationAmount']['label'] ?? '';
                $unit = $med['medicationUnit']['label'] ?? '';
                $parts = array_filter([$slotLabel, $type, $qty, $unit]);
                $line = implode(' ', $parts);
                $notes = $this->cleanHtml($med['medicationNotes'] ?? '');
                if ($notes) $line .= ' – ' . $notes;
                $medications[] = $line;
            }
        }

        $allergyText = trim(strip_tags(html_entity_decode($animal['allergies'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $allergies = ($allergyText && !$this->isBoilerplate($allergyText)) ? $allergyText : '';

        return [
            'pet_id' => $petId,
            'animal_name' => trim(($animal['first_name'] ?? $res['animal_name'] ?? '') . ($ownerRow['last_name'] ? ' ' . $ownerRow['last_name'] : '')),
            'color' => $animal['color_and_markings_gingr'] ?? null,
            'breed' => $breeds->get((string)($animal['breed'] ?? '')) ?? null,
            'owner_id' => $res['owner_id'] ?? null,
            'owner_name' => trim(($ownerRow['first_name'] ?? '') . ' ' . ($ownerRow['last_name'] ?? '')),
            'owner_phone' => $ownerRow['cell_phone'] ?? null,
            'emergency_contact_name' => $ownerRow['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $ownerRow['emergency_contact_phone'] ?? null,
            'checkin_date' => $res['checkin_date'],
            'checkout_date' => $res['checkout_date'],
            'checkout_time' => $res['checkout_time'] ?? null,
            'cabin_name' => $res['cabin_name'] ?? null,
            'food_type' => $feeding['foodType']['label'] ?? null,
            'feeding_notes' => trim($feeding['feedingNotes'] ?? '') ?: null,
            'feedings' => $feedings,
            'medications' => $medications,
            'allergies' => $allergies,
            'services_string' => null,
        ];
    }

    private function cleanHtml(string $text): string
    {
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
