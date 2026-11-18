<?php

namespace App\Services\Order;

use Exception;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeKeyService
{
    /**
     * Map country IDs to Stripe config keys.
     * Default (US/CA) is the fallback and doesn't need to be listed.
     *
     * To add Europe: add EU country IDs => 'marketplace_stripe_eu'
     */
    const COUNTRY_CONFIG_MAP = [
        230 => 'marketplace_stripe_uk',
    ];

    const DEFAULT_CONFIG = 'marketplace_stripe';

    public static function resolve(?int $countryId): array
    {
        $configKey = self::COUNTRY_CONFIG_MAP[$countryId] ?? self::DEFAULT_CONFIG;

        return config("services.{$configKey}");
    }

    public static function key(?int $countryId): string
    {
        return self::resolve($countryId)['key'];
    }

    public static function publicKey(?int $countryId): string
    {
        return self::resolve($countryId)['public_key'];
    }

    public static function configKey(?int $countryId): string
    {
        return self::COUNTRY_CONFIG_MAP[$countryId] ?? self::DEFAULT_CONFIG;
    }

    public static function resolveFromStore(): array
    {
        $store = auth()->user()?->store;

        return self::resolve($store?->country_id);
    }

    public static function keyFromStore(): string
    {
        return self::resolveFromStore()['key'];
    }

    public static function publicKeyFromStore(): string
    {
        return self::resolveFromStore()['public_key'];
    }

    /**
     * Get all unique Stripe config keys (for webhook secret iteration).
     */
    public static function allConfigKeys(): array
    {
        $keys = array_unique(array_values(self::COUNTRY_CONFIG_MAP));
        array_unshift($keys, self::DEFAULT_CONFIG);

        return $keys;
    }

    /**
     * Verify a webhook by trying all configured secrets for a given secret field.
     * Returns ['event' => \Stripe\Event, 'config' => array] on success.
     *
     * @throws Exception if no secret matches
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

                continue;
            }
        }

        throw new Exception(
            'Webhook signature verification failed for all configured Stripe accounts. '.
            ($lastException ? $lastException->getMessage() : '')
        );
    }
}
