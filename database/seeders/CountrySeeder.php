<?php

namespace Database\Seeders;

use App\Models\Misc\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('seeders/data/countries.json')),
            true
        );

        Country::query()->upsert(
            $rows,
            ['id'],
            ['short_name', 'name', 'currency_code', 'currency_name', 'currency_symbol', 'is_display', 'is_display_front', 'is_display_mobile', 'order']
        );
    }
}
