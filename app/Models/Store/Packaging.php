<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Packaging extends Model
{
    public const PACKING_UNIT_IN = 'IN';

    public const PACKING_UNIT_CM = 'CM';

    public const PACKING_UNIT = [
        self::PACKING_UNIT_IN => 1,
        self::PACKING_UNIT_CM => 2,
    ];

    public const PACKING_WEIGHT_LB = 'LB';

    public const PACKING_HEIGHT_KG = 'KG';

    public const PACKING_HEIGHT_OZ = 'OZ';

    public const PACKING_HEIGHT_G = 'G';

    public const PACKING_HEIGHT_MG = 'MG';

    public const PACKING_WEIGHT = [
        self::PACKING_WEIGHT_LB => 1,
        self::PACKING_HEIGHT_KG => 2,
        self::PACKING_HEIGHT_OZ => 3,
        self::PACKING_HEIGHT_G => 4,
        self::PACKING_HEIGHT_MG => 5,
    ];

    const PACKING_UNIT_LB = 1;

    const PACKING_UNIT_KG = 2;

    const PACKING_UNIT_OZ = 3;

    const PACKING_UNIT_G = 4;

    const PACKING_UNIT_MG = 5;

    public const PACKING_TYPE_BOX = 'BOX';

    public const PACKING_TYPE_ENV = 'ENVELOPE';

    public const PACKING_TYPE_SOFT = 'SOFT';

    public const PACKING_TYPE = [
        self::PACKING_TYPE_BOX => 1,
        self::PACKING_TYPE_ENV => 2,
        self::PACKING_TYPE_SOFT => 3,
    ];

    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'length',
        'width',
        'height',
        'unit',
        'empty_weight',
        'empty_unit',
        'is_default',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($package): void {
            $package->uuid = Str::uuid();
        });
    }
}
