<?php

namespace App\Http\Requests\Product;

use App\Models\Product\Item;
use Illuminate\Validation\Rule;

/**
 * Verbatim port of the nested validation rule sets from backend-api's
 * ProductCreateController and ProductUpdateController. These only run
 * when status === Item::STATUS_ACTIVE — see ProductCreateController /
 * ProductUpdateController, which replicate backend-api's behavior of
 * skipping validation entirely for any other status.
 */
class ProductValidationRules
{
    public static function forCreate(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Item::STATUS))],

            // About section rules
            'about.title' => ['required', 'string'],
            'about.type' => ['required', 'string', Rule::in(array_keys(Item::TYPES))],
            'about.description' => ['required', 'string'],

            // Nested customizations array (if provided). backend-api defines the
            // 'about.customizations' key twice in this rule set — ['sometimes','array']
            // then a closure-only array — and PHP array-literal semantics mean the
            // second silently replaces the first, so only the closure ever actually
            // runs. Replicated as-is (not merged) to match real behavior.
            'about.customizations' => [
                function ($attribute, $value, $fail) {
                    if (! empty($value) && empty($value['description']) && empty($value['limit']) && empty($value['files'])) {
                        $fail('At least one of description, limit, or files must be present in customizations.');
                    }
                },
            ],
            'about.customizations.enabled' => ['required_with:about.customizations', 'boolean'],
            'about.customizations.description' => ['nullable', 'string'],
            'about.customizations.limit' => ['required_with:about.customizations', 'integer', 'min:1', 'max:1024'],
            'about.customizations.attach_file' => ['nullable', 'boolean'], // 10MB max size

            // Images: Accept an array with up to 15 items, each file max 10MB (10MB = 10240 KB)
            // changing it to 250 as Shopify Supports 250
            'about.images' => ['nullable', 'array', 'max:250'],
            'about.images.*' => ['uuid', 'exists:galleries,uuid'],
            // origin
            'about.origin' => ['required', 'string'],

            // Price and inventory section
            'price_inventory.allow_overselling' => ['sometimes', 'boolean'],
            'price_inventory.is_global_pricing' => ['required', 'boolean'],
            'price_inventory.domestic_price' => ['nullable', 'numeric', 'required_if:item_variations.vary_by.price,false'],
            'price_inventory.global_price' => ['nullable', 'numeric',
                function ($attribute, $value, $fail) {
                    if (request()->input('price_inventory.is_global_pricing') === true
                        && request()->input('item_variations.vary_by.price') === false
                        && $value === null
                    ) {
                        $fail('The '.$attribute.' field is required');
                    }
                }],
            'price_inventory.sku' => ['nullable', 'string'],
            'price_inventory.gtin' => ['nullable', 'string'],
            'price_inventory.weight' => ['nullable', 'numeric'],
            'price_inventory.unit' => ['nullable', 'string', Rule::in(array_keys(Item::ITEM_WEIGHT))],
            'price_inventory.stock_quantity' => ['nullable', 'numeric'],

            // Category and tags
            'category_tags.category' => ['required', 'uuid', 'exists:categories,uuid'],
            'category_tags.tags' => ['nullable', 'array'],
            'category_tags.tags.*' => ['string'],

            // Section
            'section' => ['nullable', 'array'],
            'section.*' => ['uuid'],
            // policy is mandatory but during the creation of first few items, the policy might or might not be available
            // and hence, we put policy as optional here, and then when the policy is created, we attach
            // default policy to items which was created with null policies
            'policy' => ['sometimes'],

            // Variants Validation
            'item_variations' => ['nullable', 'array'],
            'item_variations.variants' => ['nullable', 'array'],
            'item_variations.variants.*.name' => ['required_with:item_variations.variants', 'string'],
            'item_variations.variants.*.options' => ['required_with:item_variations.variants.*.name', 'array', 'min:1'],
            'item_variations.variants.*.options.*' => ['required_with:item_variations.variants.*.options', 'string'],

            // Combination Validation
            'item_variations.combination' => ['nullable', 'array'],
            'item_variations.combination.*.variants' => ['required_with:item_variations.combination', 'array'],
            'item_variations.combination.*.variants.*.type' => ['required_with:item_variations.combination.*.variants', 'string'],
            'item_variations.combination.*.variants.*.value' => ['required_with:item_variations.combination.*.variants.*.type', 'string'],
            'item_variations.combination.*.domestic_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.global_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.sku' => ['nullable', 'string'],
            'item_variations.combination.*.gtin' => ['nullable', 'string'],
            'item_variations.combination.*.quantity' => ['nullable', 'integer', 'min:0'],
            'item_variations.combination.*.weight' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.is_visible' => ['required', 'bool'],
            'item_variations.combination.*.allow_overselling' => ['sometimes', 'bool'],
            'item_variations.combination.*.image' => ['nullable', 'uuid', 'exists:galleries,uuid'],

            // Vary By Validation
            'item_variations.vary_by' => ['nullable', 'array'],
            'item_variations.vary_by.sku' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.quantity' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.price' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.weight' => ['required_with:item_variations.vary_by', 'boolean'],
        ];
    }

    public static function forUpdate(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Item::STATUS))],

            // About section rules
            'about.title' => ['required', 'string'],
            'about.type' => ['required', 'string', Rule::in(array_keys(Item::TYPES))],
            'about.description' => ['sometimes', 'nullable', 'string'],
            'about.is_description_edited' => ['required', 'boolean'],

            // Same backend-api duplicate-key overwrite as the create rules above —
            // only the closure applies, not ['sometimes','array'].
            'about.customizations' => [
                function ($attribute, $value, $fail) {
                    if (! empty($value) && empty($value['description']) && empty($value['limit']) && empty($value['attach_file'])) {
                        $fail('At least one of description, limit, or attach file must be present in customizations.');
                    }
                },
            ],
            'about.customizations.enabled' => ['required_with:about.customizations', 'boolean'],
            'about.customizations.description' => ['nullable', 'string'],
            'about.customizations.limit' => [
                function ($attribute, $value, $fail) {
                    if (
                        ! empty($value) && $value > 0
                        && request()->input('about.customizations.enabled') === true
                        && request()->input('about.customizations.description_enabled') === true
                        && request()->input('about.customizations.description') !== null
                    ) {
                        $fail('The '.$attribute.' field is required');
                    }
                },
            ],
            'about.customizations.description_enabled' => ['nullable', 'boolean'],
            'about.customizations.attach_file' => ['nullable', 'boolean'], // 10MB max size

            // Images: Accept an array with up to 15 items, each file max 10MB (10MB = 10240 KB)
            'about.images' => ['nullable', 'array', 'max:250'],
            'about.images.*' => ['uuid', 'exists:galleries,uuid'],
            'about.origin' => ['sometimes', 'string'],

            // Price and inventory section
            'price_inventory.allow_overselling' => ['sometimes', 'boolean'],
            'price_inventory.is_global_pricing' => ['required', 'boolean'],
            'price_inventory.domestic_price' => ['nullable', 'numeric', 'required_if:item_variations.vary_by.price,false'],
            'price_inventory.global_price' => ['nullable', 'numeric',
                function ($attribute, $value, $fail) {
                    if (request()->input('price_inventory.is_global_pricing') === true
                        && request()->input('item_variations.vary_by.price') === false
                        && $value === null
                    ) {
                        $fail('The '.$attribute.' field is required');
                    }
                }],
            'price_inventory.uuid' => ['sometimes', 'uuid'],
            'price_inventory.sku' => ['nullable', 'string'],
            'price_inventory.gtin' => ['nullable', 'string'],
            'price_inventory.weight' => ['nullable', 'numeric'],
            'price_inventory.unit' => ['nullable', 'string', Rule::in(array_keys(Item::ITEM_WEIGHT))],
            'price_inventory.stock_quantity' => ['nullable', 'numeric'],

            // Category and tags
            'category_tags.category' => ['required', 'uuid', 'exists:categories,uuid'],
            'category_tags.tags' => ['nullable', 'array'],
            'category_tags.tags.*' => ['string'],

            // Section - array of UUIDs
            'section' => ['nullable', 'array'],
            'section.*' => ['uuid'],
            'policy' => ['sometimes'],

            // Variants Validation
            'item_variations' => ['nullable', 'array'],
            'item_variations.variants' => ['nullable', 'array'],
            'item_variations.variants.*.name' => ['required_with:item_variations.variants', 'string'],
            'item_variations.variants.*.options' => ['required_with:item_variations.variants.*.name', 'array', 'min:1'],
            'item_variations.variants.*.options.*' => ['required_with:item_variations.variants.*.options', 'string'],

            // Combination Validation
            'item_variations.combination' => ['nullable', 'array'],
            'item_variations.combination.*.variants' => ['required_with:item_variations.combination', 'array'],
            'item_variations.combination.*.variants.*.type' => ['required_with:item_variations.combination.*.variants', 'string'],
            'item_variations.combination.*.variants.*.value' => ['required_with:item_variations.combination.*.variants.*.type', 'string'],
            'item_variations.combination.*.domestic_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.sku' => ['nullable', 'string'],
            'item_variations.combination.*.gtin' => ['nullable', 'string'],
            'item_variations.combination.*.quantity' => ['nullable', 'integer'],
            'item_variations.combination.*.weight' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.is_visible' => ['sometimes', 'boolean'],
            'item_variations.combination.*.uuid' => ['sometimes', 'uuid'],
            'item_variations.combination.*.allow_overselling' => ['sometimes', 'bool'],
            'item_variations.combination.*.image' => ['nullable', 'uuid', 'exists:galleries,uuid'],

            // Vary By Validation
            'item_variations.vary_by' => ['nullable', 'array'],
            'item_variations.vary_by.uuid' => ['sometimes', 'uuid'],
            'item_variations.vary_by.sku' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.quantity' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.price' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.weight' => ['required_with:item_variations.vary_by', 'boolean'],

            'discount' => ['nullable', 'array'],
            'discount.value' => ['required_with:discount', 'numeric', 'between:1,100'],
            'discount.start_date' => ['required_if:discount.is_limited,0', 'date', 'nullable'],
            'discount.end_date' => ['required_if:discount.is_limited,0', 'date', 'after:discount.start_date', 'nullable'],
            'discount.is_limited' => ['nullable', 'boolean'],
            'is_edited' => ['sometimes', 'array'],
        ];
    }
}
