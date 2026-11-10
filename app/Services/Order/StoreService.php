<?php

namespace App\Services\Order;

use App\Models\Store\Store;
use App\Models\Store\StoreSetting;

class StoreService
{
    public function getStoreSettings(): ?StoreSetting
    {
        return resolve(Store::class)?->load(['settings'])->settings;
    }
}
