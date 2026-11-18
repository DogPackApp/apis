<?php

namespace App\Services;

use Exception;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeKeyService
{
    /**
     * Map country IDs to Stripe config keys. Default (US/CA) is the fallback
     * and doesn't need to be listed.
     *
     * @var array<int, string>
     */
    public const COUNTRY_CONFIG_MAP = [
        230 => 'marketplace_stripe_uk',
    ];

    public const DEFAULT_CONFIG = 'marketplace_stripe';

    /**
     * @return array<string, mixed>
     */
    public static function resolve(?int $countryId): array
    {
        return config('services.'.self::configKey($countryId));
    }

    public static function key(?int $countryId): string
    {
        return self::resolve($countryId)['key'];
    }

    public static function configKey(?int $countryId): string
    {
        return self::COUNTRY_CONFIG_MAP[$countryId] ?? self::DEFAULT_CONFIG;
    }

    /**
     * @return array<int, string>
     */
    public static function allConfigKeys(): array
    {
        $keys = array_unique(array_values(self::COUNTRY_CONFIG_MAP));
        array_unshift($keys, self::DEFAULT_CONFIG);

        return $keys;
    }

    /**
     * Verify a webhook by trying every configured secret for one field.
     *
     * @return array{event: Event, config: array<string, mixed>}
     *
     * @throws Exception
     */
    public static function verifyWebhook(string $payload, string $sigHeader, string $secretField = 'wb_secret'): array
    {
        $lastException = null;

        foreach (self::allConfigKeys() as $configKey) {
            $config = config("services.{$configKey}");
            $secret = $config[$secretField] ?? null;

            if (empty($secret)) {
                continue;
            }

            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);

                return [
                    'event' => $event,
                    'config' => $config,
                ];
            } catch (SignatureVerificationException $e) {
                $lastException = $e;
            }
        }

        throw new Exception(
            'Webhook signature verification failed for all configured Stripe accounts. '.
            ($lastException ? $lastException->getMessage() : '')
        );
    }
}
