<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductFieldOwnership extends Model
{
    public const PRODUCT_FIELD_OWNERSHIP = [
        'dogpack' => 'dogpack',
        'shopify' => 'shopify',
    ];

    protected $fillable = [
        'item_id',
        'field_name',
        'ownership',
    ];

    public static function isOwnedByDogpack(int $itemId, string $fieldName): bool
    {
        return self::query()
            ->where(['item_id' => $itemId])
            ->where('field_name', $fieldName)
            ->where('ownership', self::PRODUCT_FIELD_OWNERSHIP['dogpack'])
            ->exists();
    }
}
