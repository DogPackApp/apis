<?php

namespace App\Services;

use App\Models\Seller\Seller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class SellerService
{
    /**
     * @param  array{email: string, password: string, token: string, password_confirmation: string}  $credentials
     */
    public function resetPassword(array $credentials): mixed
    {
        return Password::broker('sellers')->reset(
            $credentials,
            function (Seller $seller, string $password): void {
                $seller->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );
    }
}
