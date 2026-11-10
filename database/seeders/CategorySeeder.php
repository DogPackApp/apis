<?php

namespace Database\Seeders;

use App\Models\Product\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(
            File::get(database_path('seeders/data/categories.json')),
            true
        );

        Category::query()->upsert(
            $rows,
            ['id'],
            ['uuid', 'parent_id', 'name', 'images', 'ht_code', 'slug', 'order', 'is_active', 'images_thumb', 'banner_image']
        );
    }
}
