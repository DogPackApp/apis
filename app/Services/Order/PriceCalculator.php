<?php

namespace App\Services\Order;

class PriceCalculator
{
    public function __construct(
        private CountryService $countryService = new CountryService,
        private CurrencyService $currencyService = new CurrencyService,
    ) {}

    private function customRound(float $value): float
    {
        $floor = floor($value);
        $decimal = $value - $floor;

        if ($decimal === 0.0) {
            return $value;
        }

        if ($decimal <= 0.50) {
            return $floor + 0.50;
        }

        return ceil($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDiscountedPrice(float|int $price, float|int $discount, ?int $countryId = null, ?string $userCurrency = null): array
    {
        $storeCurrency = $this->countryService->getCurrencyCodeByCountryId($countryId);
        $applieddiscount = 0;
        $applieddiscountwithOutFx = 0;

        $originalPrice = $price / 100;

        $rates = null;

        if ($userCurrency !== $storeCurrency) {
            $rates = $this->currencyService->convert($originalPrice, $storeCurrency, $userCurrency);
            $convertedPriceWithOutFx = $rates['convertedPrice'];
            $fxBufferRate = 1.015;
        } else {
            $convertedPriceWithOutFx = $originalPrice;
            $fxBufferRate = 1;
        }

        $priceWithFxRate = $convertedPriceWithOutFx * $fxBufferRate;
        $convertedPrice = $priceWithFxRate;
        if ($userCurrency !== $storeCurrency) {
            $convertedPrice = $this->customRound($priceWithFxRate);
        }

        $fxBufferPrice = $convertedPrice - $convertedPriceWithOutFx;
        $discountedPrice = $convertedPrice;

        if ($discount > 0) {
            $applieddiscount = (($convertedPrice * $discount) / 100);
            $applieddiscountwithOutFx = (($convertedPriceWithOutFx * $discount) / 100);
            $discountedPrice = $convertedPrice - $applieddiscount;
            if ($fxBufferPrice > 0) {
                $fxBufferPrice = $fxBufferPrice - (($fxBufferPrice * $discount) / 100);
            }
        }

        return [
            'originalPrice' => $originalPrice,
            'convertedPriceWithOutFx' => $convertedPriceWithOutFx,
            'fxBufferRate' => $fxBufferPrice,
            'convertedPrice' => $convertedPrice,
            'discount' => $discount,
            'discountedPrice' => $discountedPrice,
            'exchangeRate' => $rates['rate'] ?? null,
            'original' => [
                'priceWithOutFx' => $convertedPriceWithOutFx / ($rates['rate'] ?? 1),
                'price' => $convertedPrice / ($rates['rate'] ?? 1),
                'discount' => $discount,
                'applieddiscount' => $applieddiscount / ($rates['rate'] ?? 1),
                'applieddiscountwithOutFx' => $applieddiscountwithOutFx / ($rates['rate'] ?? 1),
                'discountedPrice' => $discountedPrice / ($rates['rate'] ?? 1),
                'fxBufferPriceAfterDiscount' => $fxBufferPrice / ($rates['rate'] ?? 1),
                'currency' => $storeCurrency,
                'exchangeRate' => $rates['rate'] ?? 1,
            ],
            'converted' => [
                'priceWithOutFx' => $convertedPriceWithOutFx,
                'price' => $convertedPrice,
                'discount' => $discount,
                'applieddiscount' => $applieddiscount,
                'applieddiscountwithOutFx' => $applieddiscountwithOutFx,
                'discountedPrice' => $discountedPrice,
                'fxBufferPriceAfterDiscount' => $fxBufferPrice,
                'currency' => $userCurrency,
                'exchangeRate' => $rates['rate'] ?? 1,
            ],
        ];
    }
}
