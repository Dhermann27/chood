<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\Cookie\CookieJar;
use Symfony\Component\Panther\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class PantherService
{
    protected Client $client;
    const SUBMIT_BUTTON_CSS = 'button[type=submit]'; // Login

    public function __construct()
    {
        $this->client = Client::createChromeClient(null, [
            '--no-sandbox',
            '--disable-gpu',  // Other flags as needed
            '--headless',
            '--disable-dev-shm-usage'
        ], [
            'port' => intval(config('services.panther.port')),
        ]);
    }

    public function loginAndStoreCookie(): void
    {
        try {
            $this->client->request('GET', config('services.panther.uris.base'));
            $crawler = $this->client->waitFor(self::SUBMIT_BUTTON_CSS);

            $form = $crawler->selectButton('Submit')->form();
            $form['username'] = config('services.panther.username');
            $form['password'] = config('services.panther.password');
            $this->client->submit($form);

            $element = $this->waitForEitherElement(['.cbw-messaging-error', 'div#in-house']);
            if (str_contains($element->attr('class'), 'cbw-messaging-error')) {
                Log::error("Form submission failed with error: " . $element->text());
            }
            $this->storeCookieJar($this->client->getCookieJar());

        } catch (\Exception $e) {
            Log::error('Error requesting base_uri:' . $e->getMessage());
        } finally {
            $this->client->quit();
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function fetchData(string $url): ?array
    {
        try {
            $cookies = $this->getExistingCookieJar();
            if (!$cookies) {

                $this->loginAndStoreCookie();
                $cookies = $this->getExistingCookieJar();
            }
            $httpClient = HttpClient::create();
            $cookieArray = [];
            if (count($cookies) > 0) {
                foreach ($cookies as $cookie) {
                    $cookieArray[] = $cookie['name'] . '=' . $cookie['value'];
                }
            }

            $response = $httpClient->request('GET', $url, [
                'headers' => [
                    'Cookie' => implode('; ', $cookieArray),
                ],
            ]);
            $data = json_decode($response->getContent(), true)['data'][0];
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            } else {
                Log::error('Failed to parse JSON: ' . json_last_error_msg());
            }
        } catch (\Exception $e) {
            Log::error('Error requesting ' . $url . ': ' . $e->getMessage());
            dd($e);
        }
        return null;
    }

    protected function getExistingCookieJar(): ?array
    {
        return Cache::get('panther_cookie_jar');
    }

    protected function storeCookieJar(CookieJar $cookieJar): void
    {
        $expiration = now()->addHour();
        $cookies = [];
        foreach ($cookieJar->all() as $cookie) {
            if ($cookie->getExpiresTime() !== null) {
                $expiration = Carbon::createFromTimestamp($cookie->getExpiresTime());
            }
            $cookies[] = ['name' => $cookie->getName(), 'value' => $cookie->getValue()];
        }
        Cache::put('panther_cookie_jar', $cookies, $expiration);
    }


    protected function waitForEitherElement(array $selectors, int $timeout = 15): ?Crawler
    {
        $endTime = time() + $timeout;

        while (time() < $endTime) {
            foreach ($selectors as $selector) {
                try {
                    $element = $this->client->getCrawler()->filter($selector);
                    if ($element->count() > 0) {
                        return $element->first();
                    }
                } catch (\InvalidArgumentException $e) {
                    continue;
                }
            }
            usleep(500000); // 500 milliseconds
        }

        throw new \RuntimeException('None of the elements were found within the timeout period.');
    }
}
