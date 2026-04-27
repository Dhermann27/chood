<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WiwService
{
    const string LOGIN_URL    = 'https://api.login.wheniwork.com/login';
    const string V2_BASE_URL  = 'https://api.wheniwork.com/2/';
    const string V3_BASE_URL  = 'https://api.wheniwork.com/v3/';
    const string APP_URL      = 'https://app.wheniwork.com';
    const string TOKEN_CACHE_KEY = 'wiw_token';
    const int    TOKEN_TTL_HOURS = 24 * 7;
    const string USER_AGENT   = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    /**
     * @throws ConnectionException
     */
    private function token(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        $response = Http::post(self::LOGIN_URL, [
            'email'    => config('services.wiw.email'),
            'password' => config('services.wiw.password'),
        ]);

        if (!$response->successful()) {
            Log::error('WIW login failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('WIW authentication failed.');
        }

        $token = $response->json('token');
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addHours(self::TOKEN_TTL_HOURS));

        return $token;
    }

    /**
     * @throws ConnectionException
     */
    public function getV2(string $path, array $query = []): array
    {
        $response = $this->client()->get(self::V2_BASE_URL . $path, $query);

        if (!$response->successful()) {
            Log::error("WIW v2 request failed: {$path}", ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception("WIW v2 request failed: {$path} (HTTP {$response->status()}): {$response->body()}");
        }

        return $response->json();
    }

    /**
     * @throws ConnectionException
     */
    public function getV3(string $path, array $query = []): array
    {
        $response = $this->client()->get(self::V3_BASE_URL . $path, $query);

        if (!$response->successful()) {
            Log::error("WIW v3 request failed: {$path}", ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception("WIW v3 request failed: {$path} (HTTP {$response->status()}): {$response->body()}");
        }

        return $response->json();
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token())->withHeaders([
            'User-Agent'       => self::USER_AGENT,
            'Referer'          => self::APP_URL,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
        ]);
    }
}
