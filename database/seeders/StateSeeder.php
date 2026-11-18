<?php

namespace Database\Seeders;

use App\Models\Misc\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('seeders/data/states.json')),
            true
        );

        State::query()->upsert($rows, ['id'], ['country_id', 'short_name', 'name']);
    }
}
