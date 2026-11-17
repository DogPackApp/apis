<?php

namespace App\Http\Requests\Misc;

use Illuminate\Foundation\Http\FormRequest;

class StateListRequest extends FormRequest
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
            'country_id' => ['required', 'integer'],
        ];
    }
}
