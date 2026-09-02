<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:stores,name'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string'],
            'social_links' => ['nullable', 'array'],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }
}
