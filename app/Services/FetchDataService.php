<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FetchDataService
{
    /**
     * Fetch from a Gingr /api/v1/* endpoint using the API key.
     *
     * @throws Exception
     */
    public function fetchApi(string $endpoint, array $params = []): array
    {
        $url = config('services.gingr.uris.' . $endpoint, $endpoint);
        $params['key'] = config('services.gingr.api_key');

        $cookies = Cache::get('gingr_session_cookies') ?? $this->authenticate();
        $cookieHeader = collect($cookies)->map(fn($value, $name) => "$name=$value")->implode('; ');

        $response = Http::withHeaders(['Cookie' => $cookieHeader])->get($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr API request failed [{$response->status()}]: $url");
        }

        return $response->json();
    }

    /**
     * Fetch from a Gingr session-authenticated endpoint (e.g. /owners/get_form_data).
     *
     * @throws Exception
     */
    public function fetchWithSession(string $path): array
    {
        $cookies = Cache::get('gingr_session_cookies') ?? $this->authenticate();

        $cookieHeader = collect($cookies)
            ->map(fn($value, $name) => "$name=$value")
            ->implode('; ');

        $response = Http::withHeaders([
            'Cookie' => $cookieHeader,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
        ])->get(config('services.gingr.uris.' . $path, $path));

        if (!$response->successful()) {
            throw new Exception("Gingr session request failed [{$response->status()}]: $path");
        }

        return $response->json();
    }

    /**
     * @throws Exception
     */
    private function authenticate(): array
    {
        $jar = new CookieJar();

        $response = Http::withOptions([
            'allow_redirects' => false,
            'cookies' => $jar,
        ])->asForm()->post(config('services.gingr.uris.login'), [
            'identity' => config('services.gingr.username'),
            'password' => config('services.gingr.password'),
        ]);

        if ($response->status() !== 302) {
            throw new Exception('Gingr authentication failed: ' . $response->status());
        }

        $cookies = [];
        foreach ($jar as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        // Cache just under 3 days — CSRF cookie is shortest-lived at 3 days
        Cache::put('gingr_session_cookies', $cookies, now()->addMinutes(4318));

        return $cookies;
    }
}
