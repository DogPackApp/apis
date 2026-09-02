<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;

trait InteractsWithSellerAuth
{
    protected function setUpSellerAuth(): void
    {
        if (! Schema::hasTable('sellers')) {
            $this->createSellerTables();

            Artisan::call('migrate', [
                '--path' => base_path('vendor/laravel/passport/database/migrations'),
                '--realpath' => true,
                '--force' => true,
                '--no-interaction' => true,
            ]);

            app(ClientRepository::class)->createPersonalAccessGrantClient(
                'Test Personal Access Client',
                'sellers',
            );
        }
    }

    protected function createSellerTables(): void
    {
        Schema::create('sellers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 100)->nullable();
            $table->string('password');
            $table->string('google_id')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('twofa_enabled')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('seller_otps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('seller_id');
            $table->integer('otp');
            $table->tinyInteger('is_active')->default(0);
            $table->string('login_type')->default('seller');
            $table->timestamps();
        });

        Schema::create('onboarding_statuses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->tinyInteger('is_product')->default(0);
            $table->tinyInteger('is_shipping')->default(0);
            $table->tinyInteger('is_store_setting')->default(0);
            $table->tinyInteger('is_finance')->default(0);
            $table->boolean('is_subscribe')->nullable()->default(false);
        });

        Schema::create('password_resets', function (Blueprint $table): void {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}
