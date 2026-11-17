<?php

$decodeKey = static function (string $environmentVariable): ?string {
    $encodedKey = env($environmentVariable);

    if ($encodedKey === null || $encodedKey === '') {
        return null;
    }

    $decodedKey = base64_decode($encodedKey, true);

    if ($decodedKey === false) {
        throw new RuntimeException(sprintf(
            'The %s environment variable must contain a valid Base64-encoded Passport key.',
            $environmentVariable,
        ));
    }

    return $decodedKey;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'marketplace',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. Keys are Base64-encoded PEM in the environment.
    | When either value is empty, Passport falls back to storage/oauth-*.key.
    |
    */

    'private_key' => $decodeKey('PASSPORT_PRIVATE_KEY_BASE64'),

    'public_key' => $decodeKey('PASSPORT_PUBLIC_KEY_BASE64'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
