<?php

namespace App\Services\Order;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    protected string $appId;

    protected int $cacheTtl;

    protected $apiUrl = 'https://openexchangerates.org/api';

    public function __construct()
    {
        $this->appId = config('services.open_exchange_api_key');
        $this->cacheTtl = config('currency.cache_ttl', 3600);
    }

    /**
     * Fetch fresh rates from OpenExchangeRates.
     *
     * @return array{base: string, rates: array<string,float>, timestamp: Carbon}
     *
     * @throws \Exception
     */
    protected function fetchRates(): array
    {
        $url = "{$this->apiUrl}/latest.json?app_id={$this->appId}";
        $resp = Http::get($url);

        if ($resp->failed()) {
            throw new \Exception('Failed to fetch exchange rates from OpenExchangeRates.');
        }

        $json = $resp->json();
        if (! isset($json['rates'])) {
            throw new \Exception('Unexpected response structure from exchange-rates API.');
        }

        return [
            'base' => $json['base'] ?? 'USD',
            'rates' => $json['rates'],
            'timestamp' => Carbon::createFromTimestamp($json['timestamp'] ?? time()),
        ];
    }

    /**
     * Get rates from cache or (on miss) from the API.
     */
    protected function getRates(): array
    {
        return Cache::remember('exchange_rates', $this->cacheTtl, function () {
            return $this->fetchRates();
        });
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @param  string  $from  e.g. 'CAD'
     * @param  string  $to  e.g. 'USD'
     * @return array{convertedPrice: float, rate: float} rounded to 2 decimals
     *
     * @throws \InvalidArgumentException
     */
    public function convert(float $amount, string $from, string $to): array
    {
        if ($from == $to) {
            return [
                'convertedPrice' => $amount,
                'rate' => 1,
            ];
        }
        $data = $this->getRates();
        $rates = $data['rates'];

        if (! isset($rates[$from]) || ! isset($rates[$to])) {
            throw new \InvalidArgumentException("Currency code not supported: {$from} or {$to}");
        }

        // convert $amount in $from → USD → $to
        $inUsd = $amount / $rates[$from];
        $out = $inUsd * $rates[$to];

        $directRate = $rates[$to] / $rates[$from];

        return [
            'convertedPrice' => $out,
            'rate' => $directRate,
        ];
    }

    /**
     * When were these rates last updated?
     */
    public function lastUpdatedAt(): Carbon
    {
        return $this->getRates()['timestamp'];
    }
}
