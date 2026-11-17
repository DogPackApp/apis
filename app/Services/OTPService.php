<?php

namespace App\Services;

use App\Exceptions\SellerIncorrectOTPException;
use App\Models\Seller\Seller;
use App\Models\Seller\SellerOtps;

class OTPService
{
    public string $otp;

    public function __construct(public Seller $seller, private string $loginType = 'seller') {}

    public function generate(): self
    {
        $this->otp = ! app()->isProduction()
            ? '1234'
            : (string) random_int(1000, 9999);

        $sellerOtp = new SellerOtps;
        $sellerOtp->seller_id = $this->seller->id;
        $sellerOtp->otp = $this->otp;
        $sellerOtp->is_active = 1;
        $sellerOtp->login_type = $this->loginType;
        $sellerOtp->save();

        return $this;
    }

    public function invalidate(): self
    {
        SellerOtps::query()
            ->where([
                'seller_id' => $this->seller->id,
                'login_type' => $this->loginType,
            ])
            ->update(['is_active' => 0]);

        return $this;
    }

    /**
     * @throws SellerIncorrectOTPException
     */
    public function validate(mixed $otp): self
    {
        $sellerOtp = SellerOtps::query()
            ->where([
                'seller_id' => $this->seller->id,
                'otp' => $otp,
                'is_active' => true,
                'login_type' => $this->loginType,
            ])
            ->latest()
            ->first();

        if (! $sellerOtp) {
            throw new SellerIncorrectOTPException;
        }

        $sellerOtp->is_active = false;
        $sellerOtp->save();

        return $this;
    }
}
