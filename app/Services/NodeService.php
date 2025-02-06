<?php

namespace App\Services;

use App\Models\Report;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NodeService
{
    
    /**
     * @throws Exception
     */
    public function fetchData(string $url, array $payload): JsonResponse
    {
        if (Cache::has($payload['username'] . '_cookie') && Cache::has($payload['username'] . '_franchiseId')) {
            $franchiseId = Cache::get($payload['username'] . '_franchiseId');
            $cookies = Cache::get($payload['username'] . '_cookie');
        } else {
            $franchiseId = $this->authenticate($payload['username'], $payload['password']);
            $cookies = Cache::get($payload['username'] . '_cookie');
        }
        $cookieHeader = collect($cookies)
            ->map(fn($cookie) => "{$cookie['name']}={$cookie['value']}")
            ->implode('; ');

        if (!empty($payload['dataToPost'])) {
            $response = Http::withHeaders([
                'Cookie' => $cookieHeader
            ])->post(preg_replace('/FRANCHISE_ID/', $franchiseId, $url), $payload['dataToPost']);
        } else {
            $response = Http::withHeaders([
                'Cookie' => $cookieHeader
            ])->get(preg_replace('/FRANCHISE_ID/', $franchiseId, $url));
        }

        if ($response->successful()) {
            return response()->json($response->json(), $response->status());
        } else {
            return response()->json([
                'error' => 'Fetching failed',
                'message' => $response->json('message', 'Unknown error'),
                'output' => $response->json()
            ], $response->status());
        }
    }

    /**
     * @throws Exception
     */
    private function authenticate(string $ddUser, string $ddPass)
    {
        $baseUri = config('services.puppeteer.uris.auth');

        $payload = [
            'timestamp' => microtime(true),
            'data' => [['username' => $ddUser, 'password' => 'sandboxTest1!']]
        ];
        $response = Http::post($baseUri, $payload);

        if ($response->successful()) {
            $error = $response->json('error');
            if ($error) {
                throw new Exception('Authentication failed: ' . $error['details']);
            }

            $cookies = preg_split('/,\s*(?=\S+=)/', $response->header('set-cookie'));
            $cookieArray = [];
            foreach ($cookies as $cookie) {
                if (preg_match('/([^=]+)=([^;]+)(?:.*?expires=([^;]+))?(?:.*?domain=([^;]+))?/', $cookie, $matches)) {
                    $cookieArray[] = [
                        'name' => $matches[1],
                        'value' => $matches[2],
                        'expires' => $matches[3],
                        'domain' => $matches[4],
                    ];
                }
            }
            $this->storeCookie($cookieArray, $ddUser);
            $franchiseId = $response->json('data.franchises.0.id');
            Cache::put($ddUser . '_franchiseId', $franchiseId);
            return $franchiseId;
        }

        throw new Exception('Request failed: ' . $response->status());
    }

    private function storeCookie(array $cookies, string $ddUser): void
    {
        $expiresAt = Carbon::now()->addMinutes(119); // Initial expiration set to 1 hour 59 minutes from now

        foreach ($cookies as $cookie) {
            $expires = $cookie['expires'];
            // Check if the cookie has a valid expiration time (ignore -1)
            if (!empty($expires) && $expires !== '-1') {
                $cookieExpiration = Carbon::parse($expires);

                // Find the earliest expiration date
                $expiresAt = $cookieExpiration->lt($expiresAt) ? $cookieExpiration : $expiresAt;
            }

        }
        Cache::put($ddUser . '_cookie', $cookies, $expiresAt);

    }

    /**
     * @param Report $report
     * @return array
     */
    public function createPayload(Report $report): array
    {
        return [
            "username" => $report->username,
            "dataToPost" => [
                "timestamp" => time(),
                "data" => [
                    [
                        "limit" => 50,
                        "order" => [],
                        "criteria" => [
                            [
                                "key" => "dateFrom",
                                "operator" => "gte",
                                "value" => $report->report_date
                            ],
                            [
                                "key" => "dateTo",
                                "operator" => "lte",
                                "value" => $report->report_date
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
