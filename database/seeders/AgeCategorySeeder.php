<?php

namespace Database\Seeders;

use App\Models\AgeCategory;
use Illuminate\Database\Seeder;

class AgeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Junior',
                'code' => 'junior',
                'min_age' => 0,
                'max_age' => 17,
                'description' => 'Youth players under 18 years old',
            ],
            [
                'name' => 'Open',
                'code' => 'open',
                'min_age' => 18,
                'max_age' => 39,
                'description' => 'Main competitive category for adults',
            ],
            [
                'name' => 'Masters',
                'code' => 'masters',
                'min_age' => 40,
                'max_age' => 54,
                'description' => 'Experienced players aged 40-54',
            ],
            [
                'name' => 'Senior Masters',
                'code' => 'senior_masters',
                'min_age' => 55,
                'max_age' => 99,
                'description' => 'Veteran players 55 and older',
            ],
        ];

        foreach ($categories as $category) {
            AgeCategory::updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}
