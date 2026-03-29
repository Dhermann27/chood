<?php

namespace App\Jobs;

use App\Models\Icon;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoFetchIconsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $petIds,
        protected array $ownerIds
    ) {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'icons';
    }

    public function handle(FetchDataService $fetchDataService): void
    {
        $url = config('services.gingr.uris.icons');

        try {
            $output = $fetchDataService->postWithSession($url, [
                'animal_ids' => json_encode(array_map('strval', $this->petIds)),
                'owner_ids'  => json_encode(array_map('strval', $this->ownerIds)),
            ]);
        } catch (Exception $e) {
            Log::warning("GoFetchIconsJob failed: {$e->getMessage()}");
            Cache::forget('gingr_session_cookies');
            return;
        }

        $now = now();
        $templates = collect($output['icon_templates']['animal_templates'] ?? []);

        Icon::upsert(
            $templates->map(fn($t) => [
                'id'         => $t['id'],
                'title'      => $t['title'],
                'class'      => $t['class'],
                'color'      => $t['color'] ?? null,
                'group_name' => $t['group_name'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray(),
            ['id'],
            ['title', 'class', 'color', 'group_name', 'updated_at']
        );

        Icon::whereNotIn('id', $templates->pluck('id'))->delete();

        $rows = collect($output['data']['animals'] ?? [])
            ->flatMap(fn($animalData, $petId) =>
                collect($animalData['icons'] ?? [])
                    ->where('type', 'custom')
                    ->pluck('color_label_template_id')
                    ->filter()
                    ->unique()
                    ->map(fn($iconId) => ['pet_id' => $petId, 'icon_id' => $iconId])
            )->toArray();

        DB::table('dog_icons')->whereIn('pet_id', $this->petIds)->delete();

        if (!empty($rows)) {
            DB::table('dog_icons')->insertOrIgnore($rows);
        }
    }
}
