<?php

namespace App\Services;

use App\Models\Report;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\JsonResponse;
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
            'User-Agent' => self::USER_AGENT,
            'Referer' => config('services.gingr.uris.dashboard'),
        ])->get(config('services.gingr.uris.' . $path, $path));

        if (!$response->successful()) {
            throw new Exception("Gingr session request failed [{$response->status()}]: $path", $response->status());
        }

        return $response->json();
    }

    /**
     * POST to a Gingr session-authenticated endpoint with API key in the body.
     *
     * @throws Exception
     */
    public function postWithSession(string $url, array $params = []): array
    {
        $cookies = Cache::get('gingr_session_cookies') ?? $this->authenticate();
        $cookieHeader = collect($cookies)->map(fn($value, $name) => "$name=$value")->implode('; ');
        $params['key'] = config('services.gingr.api_key');

        $response = Http::withHeaders([
            'Cookie'            => $cookieHeader,
            'X-Requested-With'  => 'XMLHttpRequest',
            'Accept'            => 'application/json, text/javascript, */*; q=0.01',
            'User-Agent'        => self::USER_AGENT,
            'Referer'           => config('services.gingr.uris.dashboard'),
        ])->asForm()->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr POST request failed [{$response->status()}]: $url", $response->status());
        }

        return $response->json();
    }

    /**
     * Authenticate a specific Gingr user and return their session cookies (not cached).
     *
     * @throws Exception
     */
    public function authenticateUser(string $username, string $password): array
    {
        $jar     = new CookieJar();
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
                'identity'         => $username,
                'password'         => $password,
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
            'Cookie'            => $cookieHeader,
            'X-Requested-With'  => 'XMLHttpRequest',
            'Accept'            => 'application/json, text/javascript, */*; q=0.01',
            'User-Agent'        => self::USER_AGENT,
            'Referer'           => config('services.gingr.uris.dashboard'),
        ])->asForm()->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr report request failed [{$response->status()}]: $url");
        }

        return $response->json();
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
            'Cookie'    => $cookieHeader,
            'User-Agent'=> self::USER_AGENT,
            'Referer'   => config('services.gingr.uris.dashboard'),
        ])->asForm()->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Gingr occupancy request failed [{$response->status()}]: $url");
        }

        return $response->body();
    }

    // -------------------------------------------------------------------------
    // DD (Data Dawg) — used by Deposit Finder only
    // -------------------------------------------------------------------------

    /**
     * @throws Exception
     */
    public function fetchData(string $url, array $payload): JsonResponse
    {
        $username = $payload['username'];
        if (Cache::has("{$username}_cookie") && Cache::has("{$username}_franchiseId")) {
            $franchiseId = Cache::get("{$username}_franchiseId");
            $cookies = Cache::get("{$username}_cookie");
        } else {
            $franchiseId = $this->authenticateDd($username, $payload['password']);
            $cookies = Cache::get("{$username}_cookie");
        }

        $cookieHeader = collect($cookies)
            ->map(fn($c) => "{$c['name']}={$c['value']}")
            ->implode('; ');

        $resolvedUrl = preg_replace('/FRANCHISE_ID/', $franchiseId, $url);

        $response = !empty($payload['dataToPost'])
            ? Http::withHeaders(['Cookie' => $cookieHeader])->post($resolvedUrl, $payload['dataToPost'])
            : Http::withHeaders(['Cookie' => $cookieHeader])->get($resolvedUrl);

        return response()->json($response->json(), $response->status());
    }

    public function createPayload(Report $report): array
    {
        return [
            'username' => $report->username,
            'dataToPost' => [
                'timestamp' => time(),
                'data' => [[
                    'limit' => 50,
                    'order' => [],
                    'criteria' => [
                        ['key' => 'dateFrom', 'operator' => 'gte', 'value' => $report->report_date],
                        ['key' => 'dateTo',   'operator' => 'lte', 'value' => $report->report_date],
                    ],
                ]],
            ],
        ];
    }

    public function createConstrainedPayload(Report $report, string $key, string $operator, string $value): array
    {
        $payload = $this->createPayload($report);
        $payload['dataToPost']['data'][0]['criteria'][] = ['key' => $key, 'operator' => $operator, 'value' => $value];
        return $payload;
    }

    /**
     * @throws Exception
     */
    private function authenticateDd(string $ddUser, string $ddPass): mixed
    {
        $response = Http::post(config('services.dd.uris.auth'), [
            'timestamp' => microtime(true),
            'data' => [['username' => $ddUser, 'password' => $ddPass]],
        ]);

        if (!$response->successful()) {
            throw new Exception('DD auth request failed: ' . $response->status());
        }

        if ($error = $response->json('error')) {
            throw new Exception('DD authentication failed: ' . $error['details']);
        }

        $cookies = [];
        foreach (preg_split('/,\s*(?=\S+=)/', $response->header('set-cookie')) as $cookie) {
            if (preg_match('/([^=]+)=([^;]+)(?:.*?expires=([^;]+))?(?:.*?domain=([^;]+))?/', $cookie, $m)) {
                $cookies[] = ['name' => $m[1], 'value' => $m[2], 'expires' => $m[3], 'domain' => $m[4]];
            }
        }

        $expiresAt = Carbon::now()->addMinutes(119);
        foreach ($cookies as $c) {
            if (!empty($c['expires']) && $c['expires'] !== '-1') {
                $exp = Carbon::parse($c['expires']);
                if ($exp->lt($expiresAt)) $expiresAt = $exp;
            }
        }

        Cache::put("{$ddUser}_cookie", $cookies, $expiresAt);

        $franchiseId = $response->json('data.franchises.0.id');
        Cache::put("{$ddUser}_franchiseId", $franchiseId);

        return $franchiseId;
    }

    // -------------------------------------------------------------------------
    // Gingr
    // -------------------------------------------------------------------------

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
