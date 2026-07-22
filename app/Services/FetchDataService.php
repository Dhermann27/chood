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

        $response = Http::withHeaders($this->gingrHeaders())->get($url, $params);

        if ($response->successful() && null !== ($data = $response->json())) return $data;
        if (!in_array($response->status(), [401, 403])) {
            throw new Exception("Gingr API request failed [{$response->status()}]: $url");
        }

        $response = Http::withHeaders($this->gingrHeaders(fresh: true))->get($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr API request failed [{$response->status()}]: $url");
        }

        return $response->json() ?? throw new Exception("Gingr API returned non-JSON after re-authentication: $url");
    }

    /**
     * POST to a Gingr session-authenticated endpoint and return the raw HTML body.
     *
     * @throws Exception
     */
    public function postHtmlWithSession(string $url, array $params): string
    {
        $response = Http::withHeaders($this->gingrHeaders())->asForm()->post($url, $params);

        if ($response->successful() && !str_contains($response->body(), 'gingr_csrf_token')) {
            return $response->body();
        }
        if (!$response->successful() && !in_array($response->status(), [401, 403])) {
            throw new Exception("Gingr HTML POST failed [{$response->status()}]: $url");
        }

        $response = Http::withHeaders($this->gingrHeaders(fresh: true))->asForm()->post($url, $params);
        if (!$response->successful()) {
            throw new Exception("Gingr HTML POST failed [{$response->status()}]: $url");
        }
        return $response->body();
    }

    /**
     * Fetch the Services by Date HTML report for a given date (MM/DD/YYYY).
     *
     * @throws Exception
     */
    public function fetchServicesByDate(string $date): string
    {
        return $this->postHtmlWithSession(
            config('services.gingr.uris.servicesByDate'),
            [
                'date_from' => $date,
                'date_to' => $date,
                'locations' => [config('services.gingr.location_id')],
                'service_type' => 'addl_services',
                'type' => 'scheduled',
                'checked_out' => 'true',
            ]
        );
    }

    /**
     * Fetch from a Gingr session-authenticated endpoint (e.g. /owners/get_form_data).
     *
     * @throws Exception
     */
    public function fetchWithSession(string $path): array
    {
        $url = config('services.gingr.uris.' . $path, $path);

        $response = Http::withHeaders($this->gingrHeaders())->get($url);

        if ($response->successful() && null !== ($data = $response->json())) return $data;
        if (!in_array($response->status(), [401, 403])) {
            throw new Exception("Gingr session request failed [{$response->status()}]: $path", $response->status());
        }

        $response = Http::withHeaders($this->gingrHeaders(fresh: true))->get($url);

        if (!$response->successful()) {
            throw new Exception("Gingr session request failed [{$response->status()}]: $path", $response->status());
        }

        return $response->json() ?? throw new Exception("Gingr session returned non-JSON after re-authentication: $path");
    }

    /**
     * POST to a Gingr session-authenticated endpoint with API key in the body.
     *
     * @throws Exception
     */
    public function postWithSession(string $url, array $params = []): array
    {
        $params['key'] = config('services.gingr.api_key');

        $response = Http::withHeaders($this->gingrHeaders())->asForm()->post($url, $params);

        if ($response->successful() && null !== ($data = $response->json())) return $data;
        if (!in_array($response->status(), [401, 403])) {
            throw new Exception("Gingr POST request failed [{$response->status()}]: $url", $response->status());
        }

        $response = Http::withHeaders($this->gingrHeaders(fresh: true))->asForm()->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr POST request failed [{$response->status()}]: $url", $response->status());
        }

        return $response->json() ?? throw new Exception("Gingr POST returned non-JSON after re-authentication: $url");
    }

    /**
     * Authenticate a specific Gingr user and return their session cookies (not cached).
     *
     * @throws Exception
     */
    public function authenticateUser(string $username, string $password): array
    {
        $jar = new CookieJar();
        $headers = ['User-Agent' => self::USER_AGENT];

        $loginPage = Http::withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->get(config('services.gingr.uris.login'));

        preg_match('/<input[^>]+name=["\']gingr_csrf_token["\'][^>]+value=["\']([^"\']+)["\']/', $loginPage->body(), $m);
        $csrfToken = $m[1] ?? null;

        Http::withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->asForm()
            ->post(config('services.gingr.uris.login'), [
                'identity' => $username,
                'password' => $password,
                'gingr_csrf_token' => $csrfToken,
            ]);

        $cookies = [];
        foreach ($jar as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        if (empty($cookies['gingr_ci_session'])) {
            throw new Exception('Gingr authentication failed for user: ' . $username);
        }

        return $cookies;
    }

    /**
     * POST to a Gingr report endpoint using the provided session cookies.
     *
     * @throws Exception
     */
    public function postReport(string $url, array $params, array $cookies): array
    {
        $cookieHeader = collect($cookies)->map(fn($value, $name) => "$name=$value")->implode('; ');

        $response = Http::withHeaders([
            'Cookie' => $cookieHeader,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'User-Agent' => self::USER_AGENT,
            'Referer' => config('services.gingr.uris.dashboard'),
        ])->asForm()->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr report request failed [{$response->status()}]: $url");
        }

        return $response->json() ?? throw new Exception("Gingr report returned non-JSON response: $url");
    }

    /**
     * POST to a Gingr HTML report endpoint using the provided session cookies.
     * Returns the raw HTML body.
     *
     * @throws Exception
     */
    public function fetchOccupancy(string $url, array $params, array $cookies): string
    {
        $cookieHeader = collect($cookies)->map(fn($value, $name) => "$name=$value")->implode('; ');

        $response = Http::withHeaders([
            'Cookie' => $cookieHeader,
            'User-Agent' => self::USER_AGENT,
            'Referer' => config('services.gingr.uris.dashboard'),
        ])->asForm()->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr occupancy request failed [{$response->status()}]: $url");
        }

        return $response->body();
    }

    /**
     * @throws Exception
     */
    public function fetchHtmlWithSession(string $url): string
    {
        $response = Http::withHeaders($this->gingrHeaders())->get($url);

        if ($response->successful() && !str_contains($response->body(), 'gingr_csrf_token')) {
            return $response->body();
        }
        if (!$response->successful() && !in_array($response->status(), [401, 403])) {
            throw new Exception("Gingr HTML request failed [{$response->status()}]: $url");
        }

        $response = Http::withHeaders($this->gingrHeaders(fresh: true))->get($url);
        if (!$response->successful()) {
            throw new Exception("Gingr HTML request failed [{$response->status()}]: $url");
        }
        return $response->body();
    }

    private function gingrHeaders(bool $fresh = false): array
    {
        if ($fresh) Cache::forget('gingr_session_cookies');
        $cookies = Cache::get('gingr_session_cookies') ?? $this->authenticate();
        $cookieHeader = collect($cookies)->map(fn($v, $n) => "$n=$v")->implode('; ');

        return [
            'Cookie' => $cookieHeader,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'User-Agent' => self::USER_AGENT,
            'Referer' => config('services.gingr.uris.dashboard'),
        ];
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
            throw new Exception('Gingr authentication failed — session cookie not set. The password may have expired; check GINGR_PASSWORD in .env. CSRF token found: ' . ($csrfToken ? 'yes' : 'no'));
        }

        // Cache just under 3 days — CSRF cookie is shortest-lived at 3 days
        Cache::put('gingr_session_cookies', $cookies, now()->addMinutes(4318));

        return $cookies;
    }
}
