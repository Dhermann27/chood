<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FetchDataService
{
    const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

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

        $response = Http::withHeaders([
            'Cookie' => $cookieHeader,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'User-Agent' => self::USER_AGENT,
            'Referer' => config('services.gingr.uris.dashboard'),
        ])->get($url, $params);

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
        $headers = ['User-Agent' => self::USER_AGENT];

        // GET login page to receive CSRF token
        $loginPage = Http::withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->get(config('services.gingr.uris.login'));

        preg_match('/<input[^>]+name=["\']gingr_csrf_token["\'][^>]+value=["\']([^"\']+)["\']/', $loginPage->body(), $m);
        $csrfToken = $m[1] ?? null;

        $response = Http::withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->asForm()
            ->post(config('services.gingr.uris.login'), [
                'identity' => config('services.gingr.username'),
                'password' => config('services.gingr.password'),
                'gingr_csrf_token' => $csrfToken,
            ]);

        $cookies = [];
        foreach ($jar as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        if (empty($cookies['gingr_ci_session'])) {
            throw new Exception('Gingr authentication failed: no session cookie. CSRF token found: ' . ($csrfToken ? 'yes' : 'no'));
        }

        // Cache just under 3 days — CSRF cookie is shortest-lived at 3 days
        Cache::put('gingr_session_cookies', $cookies, now()->addMinutes(4318));

        return $cookies;
    }
}
