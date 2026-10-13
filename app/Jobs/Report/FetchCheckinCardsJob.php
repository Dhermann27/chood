<?php

namespace App\Jobs\Report;

use App\Enums\HousingServiceCodes;
use App\Models\Dog;
use App\Models\Report;
use App\Models\Timeslot;
use App\Services\FetchDataService;
use App\Traits\ParsesHtmlReport;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchCheckinCardsJob implements ShouldQueue
{
    use Queueable, ParsesHtmlReport;

    public function __construct(
        public readonly string $reportId,
        public readonly string $date,
    )
    {
        $this->onQueue('high');
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $report = Report::findOrFail($this->reportId);

        $reservations = $this->fetchReservations($fetchDataService);
        $timeslots = Timeslot::pluck('gingr_label', 'id');

        $seenPetIds = [];
        $cards = [];
        $apiNeeded = [];

        foreach ($reservations as $res) {
            $petId = (string)($res['pet_id'] ?? '');
            if (!$petId || isset($seenPetIds[$petId])) continue;
            $seenPetIds[$petId] = true;

            $dog = Dog::with(['feedings', 'medications.timeslot', 'allergies', 'owner', 'cabin'])
                ->where('pet_id', $petId)->first();

            if ($dog && $dog->owner_id) {
                $cards[] = $this->cardFromDog($dog, $res, $timeslots);
            } else {
                $ownerId = $res['owner_id'] ?? ($dog?->account_id ? (string)$dog->account_id : null);
                if (!$ownerId) continue;
                $apiNeeded[$ownerId][] = $res;
            }
        }

        // For today, include in-house boarders not already listed
        if ($this->date === now()->toDateString()) {
            $boardingCodes = [HousingServiceCodes::BRDC->value, HousingServiceCodes::BRDL->value];
            $inHouse = Dog::with(['feedings', 'medications.timeslot', 'allergies', 'owner', 'cabin'])
                ->inHouse()->whereIn('housing_code', $boardingCodes)->whereNotIn('pet_id', array_keys($seenPetIds))->get();

            foreach ($inHouse as $dog) {
                if ($dog->pet_id) {
                    $cards[] = $this->cardFromDog($dog, [], $timeslots);
                }
            }
        }

        if (empty($apiNeeded)) {
            $report->data = ['date' => $this->date, 'cards' => $this->finalize($cards), 'complete' => true];
            $report->updated_at = now();
            $report->save();
            return;
        }

        $report->data = ['date' => $this->date, 'cards' => $cards, 'pending_owners' => count($apiNeeded), 'complete' => false];
        $report->updated_at = now();
        $report->save();

        $delay = (int)config('services.gingr.queue_delay');
        foreach (array_keys($apiNeeded) as $i => $ownerId) {
            FetchOwnerCardJob::dispatch($this->reportId, $ownerId, $apiNeeded[$ownerId])
                ->delay(now()->addMilliseconds($delay * ($i + 1)));
        }
    }

    public static function finalize(array $cards): array
    {
        usort($cards, fn($a, $b) => strcmp($a['animal_name'] ?? '', $b['animal_name'] ?? ''));
        $ownerCounts = array_count_values(array_filter(array_column($cards, 'owner_id')));
        return array_map(function ($card) use ($ownerCounts) {
            $card['family'] = ($ownerCounts[$card['owner_id'] ?? ''] ?? 0) > 1;
            unset($card['owner_id']);
            return $card;
        }, $cards);
    }

    /**
     * @throws Exception
     */
    private function fetchReservations(FetchDataService $fetchDataService): array
    {
        $formatted = Carbon::parse($this->date)->format('m/d/Y');

        $html = $fetchDataService->postHtmlWithSession(
            config('services.gingr.uris.reservationsByDate'),
            [
                'date_from' => $formatted,
                'date_to' => $formatted,
                'type_ids' => config('services.gingr.ids.boarding'),
                'location_id' => config('services.gingr.location_id'),
            ]
        );

        return $this->parseReservations($html);
    }

    private function parseReservations(string $html): array
    {
        $xpath = $this->loadXPath($html);

        $lodgingCol = null;
        $headers = $xpath->query('//table[@id="resultsTable1"]/thead/tr/th');
        if ($headers) {
            foreach ($headers as $i => $th) {
                if (stripos(trim($th->textContent), 'lodging') !== false) {
                    $lodgingCol = $i;
                    break;
                }
            }
        }

        $rows = $xpath->query('//table[@id="resultsTable1"]/tbody/tr');
        if (!$rows) return [];

        $results = [];
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 5) continue;

            $animalLink = $xpath->query('.//a[contains(@href,"/animals/view/id/")]', $cells->item(1))->item(0);
            $ownerLink = $xpath->query('.//a[contains(@href,"/owners/view/id/")]', $cells->item(2))->item(0);
            if (!$animalLink || !$ownerLink) continue;

            preg_match('/\/animals\/view\/id\/(\d+)/', $animalLink->getAttribute('href'), $am);
            preg_match('/\/owners\/view\/id\/(\d+)/', $ownerLink->getAttribute('href'), $om);
            $petId = $am[1] ?? null;
            $ownerId = $om[1] ?? null;
            if (!$petId || !$ownerId) continue;

            $startSpan = $xpath->query('.//span[@class="sorter"]', $cells->item(3))->item(0);
            $endSpan = $xpath->query('.//span[@class="sorter"]', $cells->item(4))->item(0);

            $cabinName = null;
            if ($lodgingCol !== null && $cell = $cells->item($lodgingCol)) {
                $br = $xpath->query('.//br', $cell)->item(0);
                $cabinName = $br?->nextSibling
                    ? (trim($br->nextSibling->textContent) ?: null)
                    : (trim($cell->textContent) ?: null);
            }

            $results[] = [
                'pet_id' => $petId,
                'owner_id' => $ownerId,
                'animal_name' => trim($animalLink->textContent),
                'checkin_date' => $startSpan
                    ? Carbon::createFromTimestamp((int)$startSpan->getAttribute('title'))->format('m/d/Y')
                    : Carbon::parse($this->date)->format('m/d/Y'),
                'checkout_date' => $endSpan
                    ? Carbon::createFromTimestamp((int)$endSpan->getAttribute('title'))->format('m/d/Y')
                    : null,
                'checkout_time' => $endSpan
                    ? Carbon::createFromTimestamp((int)$endSpan->getAttribute('title'))->format('g:ia')
                    : null,
                'cabin_name' => $cabinName,
            ];
        }

        return $results;
    }

    private function cardFromDog(Dog $dog, array $res, $timeslots): array
    {
        $feedings = $dog->feedings->map(function ($f) use ($timeslots) {
            $label = $timeslots[$f->timeslot_id] ?? '';
            if (str_contains($label, 'Owner Provided') || str_starts_with($label, 'OP -')) return null;
            $label = preg_replace('/^Lunch\s*-\s*.+/i', 'Lunch', $label);
            $parts = array_filter([$label, $f->quantity, $f->unit]);
            $line = implode(' ', $parts);
            return $f->description ? "$line: {$f->description}" : $line;
        })->filter()->values()->all();

        $medications = $dog->medications->map(function ($m) {
            if ($m->type === 'Medical Condition' || $m->type === 'Handling') {
                return $m->description;
            }
            $label = $m->timeslot?->gingr_label ?? '';
            $parts = array_filter([$label, $m->type, $m->quantity, $m->unit]);
            $line = implode(' ', $parts);
            return $m->description ? "$line – {$m->description}" : $line;
        })->filter()->values()->all();

        $allergies = $dog->allergies->pluck('description')->implode('; ');

        return [
            'pet_id' => $dog->pet_id,
            'animal_name' => trim(($dog->display_name ?? $dog->firstname) . ($dog->lastname ? ' ' . $dog->lastname : '')),
            'color' => $dog->color,
            'breed' => $dog->breed,
            'owner_id' => $res['owner_id'] ?? ($dog->account_id ? (string)$dog->account_id : null),
            'owner_name' => $dog->owner ? trim("{$dog->owner->first_name} {$dog->owner->last_name}") : null,
            'owner_phone' => $dog->owner?->cell_phone,
            'emergency_contact_name' => $dog->owner?->emergency_contact_name,
            'emergency_contact_phone' => $dog->owner?->emergency_contact_phone,
            'checkin_date' => $res['checkin_date'] ?? $dog->checkin?->format('m/d/Y'),
            'checkout_date' => $res['checkout_date'] ?? $dog->checkout?->format('m/d/Y'),
            'checkout_time' => $res['checkout_time'] ?? null,
            'cabin_name' => $res['cabin_name'] ?? $dog->cabin?->cabinName,
            'food_type' => $dog->food_type,
            'feeding_notes' => $dog->feeding_notes,
            'feedings' => $feedings,
            'medications' => $medications,
            'allergies' => $allergies,
            'services_string' => $dog->services_string,
        ];
    }
}
