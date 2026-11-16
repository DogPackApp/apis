<?php

use Illuminate\Support\Env;

uses(Tests\TestCase::class);

afterEach(function () {
    $repository = Env::getRepository();
    $repository->clear('PASSPORT_PRIVATE_KEY_BASE64');
    $repository->clear('PASSPORT_PUBLIC_KEY_BASE64');
});

test('passport decodes base64 keys from the environment', function () {
    $private = "-----BEGIN PRIVATE KEY-----\nprivate\n-----END PRIVATE KEY-----\n";
    $public = "-----BEGIN PUBLIC KEY-----\npublic\n-----END PUBLIC KEY-----\n";

    $repository = Env::getRepository();
    $repository->set('PASSPORT_PRIVATE_KEY_BASE64', base64_encode($private));
    $repository->set('PASSPORT_PUBLIC_KEY_BASE64', base64_encode($public));

    $config = require config_path('passport.php');

    expect($config['private_key'])->toBe($private)
        ->and($config['public_key'])->toBe($public)
        ->and($config['guard'])->toBe('marketplace');
});

test('passport leaves empty base64 keys unset', function () {
    $repository = Env::getRepository();
    $repository->set('PASSPORT_PRIVATE_KEY_BASE64', '');
    $repository->set('PASSPORT_PUBLIC_KEY_BASE64', '');

    $config = require config_path('passport.php');

    expect($config['private_key'])->toBeNull()
        ->and($config['public_key'])->toBeNull();
});

test('passport rejects a base64 key that does not decode', function () {
    Env::getRepository()->set('PASSPORT_PRIVATE_KEY_BASE64', '!!!!');

    expect(fn () => require config_path('passport.php'))
        ->toThrow(RuntimeException::class, 'PASSPORT_PRIVATE_KEY_BASE64');
});
