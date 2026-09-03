<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class SellerTwoFactorDisableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp' => ['required', 'numeric'],
        ];
    }
}
