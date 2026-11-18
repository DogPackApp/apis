<?php

namespace App\Services\Store;

use App\Models\Store\Packaging;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PackagingService
{
    public Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function create($validatedData): PackagingService
    {
        $packagingData = array_map(function ($package) {
            return [
                'uuid' => $package['uuid'] ?? Str::uuid(),
                'store_id' => $this->store->id,
                'name' => $package['name'],
                'type' => Packaging::PACKING_TYPE[$package['type']],
                'length' => $package['length'],
                'width' => $package['width'],
                'height' => $package['height'],
                'unit' => Packaging::PACKING_UNIT[$package['unit']],
                'empty_weight' => $package['empty_weight'],
                'empty_unit' => Packaging::PACKING_WEIGHT[$package['empty_unit']],
                'is_default' => $package['is_default'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $validatedData['packaging']);

        Packaging::upsert(
            $packagingData,
            ['uuid'],
            ['name', 'type', 'length', 'width', 'height', 'unit', 'empty_weight', 'empty_unit', 'is_default']
        );

        $this->makeDefault();

        return $this;
    }

    public function makeDefault(): void
    {
        $default = $this->defaultPackaging();

        if (! $default) {
            $latest = Packaging::query()
                ->where('store_id', $this->store->id)
                ->oldest()
                ->first();

            $latest?->update(['is_default' => true]);
        }
    }

    public function defaultPackaging(): Builder|Model|null
    {
        return Packaging::query()
            ->where('store_id', $this->store->id)
            ->where('is_default', true)
            ->first();
    }

    public function delete(): PackagingService
    {
        Packaging::query()
            ->where('store_id', $this->store->id)
            ->delete();

        return $this;
    }
}
