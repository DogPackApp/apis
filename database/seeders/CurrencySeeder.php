<?php

namespace Database\Seeders;

use App\Models\Misc\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('seeders/data/currencies.json')),
            true
        );

        Currency::query()->upsert($rows, ['id'], ['name']);
    }
}
