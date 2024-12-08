<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class NodeController extends Controller
{
    public function loginAndStoreCookie(): void
    {
        $scraperJs = base_path('resources/js/puppeteer/scraper.js');
        $nodePath = config('services.puppeteer.nodepath');
        $baseUri = config('services.puppeteer.uris.base');
        $ddUser = config('services.puppeteer.username');
        $ddPass = config('services.puppeteer.password');

        $command = "{$nodePath} {$scraperJs} {$baseUri} {$ddUser} {$ddPass} 2>&1";
        $output = exec($command, $output, $resultCode);

        if ($resultCode === 0) {
            $this->storeCookie(json_decode($output, true));
        } else {
            throw new Exception("Scraping failed: " . $output);
        }
    }

    public function fetchData(string $url): JsonResponse
    {
        $cookies = Cache::get('puppeteer_cookie');
        if (!$cookies) {
            $this->loginAndStoreCookie();
            $cookies = Cache::get('puppeteer_cookie');
        }

        $scraperJs = base_path('resources/js/puppeteer/fetcher.js');
        $nodePath = config('services.puppeteer.nodepath');
        $escapedUrl = escapeshellarg($url);
        $escapedCookies = escapeshellarg(json_encode($cookies));

        $command = "{$nodePath} {$scraperJs} {$escapedUrl} {$escapedCookies} 2>&1";
        $output = exec($command, $output, $resultCode);

        if ($resultCode === 0) {
            return response()->json(json_decode($output, true));
        }

        return response()->json([
            'error' => 'Fetching failed',
            'output' => $output,
            'resultCode' => $resultCode
        ], 500);
    }

    public function storeCookie(Array $cookies): JsonResponse
    {
        $cookiesArray = [];
        $expiresAt = Carbon::now()->addMinutes(119); // Initial expiration set to 1 hour 59 minutes from now

        foreach ($cookies as $cookie) {
            $expires = $cookie['expires'];

            // Check if the cookie has a valid expiration time (ignore -1)
            if ($expires > 0) {
                $cookieExpiration = Carbon::createFromTimestamp($expires);

                // Find the earliest expiration date
                $expiresAt = $cookieExpiration->lt($expiresAt) ? $cookieExpiration : $expiresAt;
            }

            // Add cookie details to the array
            $cookiesArray[] = ['name' => $cookie['name'], 'value' => $cookie['value'], 'domain' => $cookie['domain']];
        }
        Cache::put('puppeteer_cookie', $cookiesArray, $expiresAt);

        return response()->json(['status' => 'Cookie cached successfully!'], 200);
    }

}
