<?php

namespace Database\Seeders;

use App\Models\Misc\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = collect(json_decode(
            File::get(database_path('seeders/data/languages.json')),
            true
        ))->map(fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'short_name' => $row['short_name'],
            'is_front' => $row['is_front'],
            'is_app' => $row['is_app'],
            'is_admin' => $row['is_admin'],
        ])->all();

        Language::query()->upsert($rows, ['id'], ['name', 'short_name', 'is_front', 'is_app', 'is_admin']);
    }
}
