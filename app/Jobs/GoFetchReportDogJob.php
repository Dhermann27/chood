<?php

namespace App\Jobs;

use App\Services\FetchDataService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoFetchReportDogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $ownerId,
        protected string $date,
        protected array  $petIds
    )
    {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return "report_dog_{$this->ownerId}_{$this->date}";
    }

    public function handle(FetchDataService $fetchDataService): void
    {
        $url = config('services.gingr.uris.ownerData') . $this->ownerId;

        try {
            $output = $fetchDataService->fetchWithSession($url);
        } catch (Exception $e) {
            if (in_array($e->getCode(), [404, 500])) {
                Log::warning("GoFetchReportDogJob skipping owner {$this->ownerId}: {$e->getMessage()}");

                return;
            }
            throw $e;
        }

        $expiry = Carbon::tomorrow()->startOfDay();

        foreach ($output['animals'] ?? [] as $animal) {
            $petId = (int)($animal['system_id'] ?? 0);
            if (!in_array($petId, $this->petIds)) {
                continue;
            }

            $allergiesHtml = $animal['allergies'] ?? null;
            $text = $allergiesHtml ? trim(strip_tags($allergiesHtml)) : '';
            $allergies = ($text !== '' && !$this->isBoilerplate($text))
                ? [['description' => $text]]
                : [];

            $weight = isset($animal['weight']) && $animal['weight'] !== '' ? (int)$animal['weight'] : null;

            Cache::put(
                "report_dog_{$petId}_{$this->date}",
                [
                    'gender' => match ($animal['gender'] ?? null) {
                        'Male' => 'M',
                        'Female' => 'F',
                        default => null,
                    },
                    'weight' => $weight,
                    'size_letter' => $this->sizeFromWeight($weight),
                    'allergies' => $allergies,
                ],
                $expiry
            );
        }

        $delay = config('services.gingr.queue_delay');
        if ($delay) {
            usleep(mt_rand($delay, $delay + 1000) * 1000);
        }
    }

    private function sizeFromWeight(?int $weight): ?string
    {
        if (!$weight) {
            return null;
        }

        return match (true) {
            $weight >= 40 => 'L',
            $weight >= 30 => 'LS',
            $weight >= 15 => 'S',
            $weight >= 10 => 'ST',
            default => 'T',
        };
    }

    private function isBoilerplate(string $text): bool
    {
        return (bool)preg_match('/^(none|no \w+ needed|none no \w+ needed)$/i', $text);
    }
}
