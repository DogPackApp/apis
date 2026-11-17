<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;

trait InteractsWithSellerAuth
{
    protected function setUpSellerAuth(): void
    {
        if (! Schema::hasTable('sellers')) {
            Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);

            app(ClientRepository::class)->createPersonalAccessGrantClient(
                'Test Personal Access Client',
                'sellers',
            );
        }
    }
}
