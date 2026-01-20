<?php

namespace Database\Seeders;

use App\Enums\GeographicLevel;
use App\Models\GeographicUnit;
use Illuminate\Database\Seeder;

class GeographicUnitSeeder extends Seeder
{
    public function run(): void
    {
        // ROOT - Africa
        $africa = GeographicUnit::updateOrCreate(
            ['code' => 'AF', 'level' => GeographicLevel::ROOT->value],
            [
                'name' => 'Africa',
                'local_term' => 'Continent',
                'parent_id' => null,
                'country_code' => null,
            ]
        );

        // NATIONAL - Kenya
        $kenya = GeographicUnit::updateOrCreate(
            ['code' => 'KE', 'level' => GeographicLevel::NATIONAL->value],
            [
                'name' => 'Kenya',
                'local_term' => 'Country',
                'parent_id' => $africa->id,
                'country_code' => 'KE',
            ]
        );

        // MACRO - Sample Regions in Kenya
        $centralRegion = GeographicUnit::updateOrCreate(
            ['code' => 'KE-CR', 'level' => GeographicLevel::MACRO->value],
            [
                'name' => 'Central Region',
                'local_term' => 'Region',
                'parent_id' => $kenya->id,
                'country_code' => 'KE',
            ]
        );

        // MESO - Sample Counties
        $nairobi = GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO', 'level' => GeographicLevel::MESO->value],
            [
                'name' => 'Nairobi',
                'local_term' => 'County',
                'parent_id' => $centralRegion->id,
                'country_code' => 'KE',
            ]
        );

        // MICRO - Sample Constituencies
        $westlands = GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO-WL', 'level' => GeographicLevel::MICRO->value],
            [
                'name' => 'Westlands',
                'local_term' => 'Constituency',
                'parent_id' => $nairobi->id,
                'country_code' => 'KE',
            ]
        );

        $langata = GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO-LG', 'level' => GeographicLevel::MICRO->value],
            [
                'name' => 'Langata',
                'local_term' => 'Constituency',
                'parent_id' => $nairobi->id,
                'country_code' => 'KE',
            ]
        );

        // NANO - Sample Wards
        $kangemi = GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO-WL-KG', 'level' => GeographicLevel::NANO->value],
            [
                'name' => 'Kangemi',
                'local_term' => 'Ward',
                'parent_id' => $westlands->id,
                'country_code' => 'KE',
            ]
        );

        $karenWard = GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO-LG-KR', 'level' => GeographicLevel::NANO->value],
            [
                'name' => 'Karen',
                'local_term' => 'Ward',
                'parent_id' => $langata->id,
                'country_code' => 'KE',
            ]
        );

        // ATOMIC - Sample Communities
        GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO-WL-KG-C1', 'level' => GeographicLevel::ATOMIC->value],
            [
                'name' => 'Kangemi Central',
                'local_term' => 'Community',
                'parent_id' => $kangemi->id,
                'country_code' => 'KE',
            ]
        );

        GeographicUnit::updateOrCreate(
            ['code' => 'KE-NBO-LG-KR-C1', 'level' => GeographicLevel::ATOMIC->value],
            [
                'name' => 'Karen Estate',
                'local_term' => 'Community',
                'parent_id' => $karenWard->id,
                'country_code' => 'KE',
            ]
        );

        // Add Nigeria as another example
        $nigeria = GeographicUnit::updateOrCreate(
            ['code' => 'NG', 'level' => GeographicLevel::NATIONAL->value],
            [
                'name' => 'Nigeria',
                'local_term' => 'Country',
                'parent_id' => $africa->id,
                'country_code' => 'NG',
            ]
        );

        $lagos = GeographicUnit::updateOrCreate(
            ['code' => 'NG-LA', 'level' => GeographicLevel::MACRO->value],
            [
                'name' => 'Lagos State',
                'local_term' => 'State',
                'parent_id' => $nigeria->id,
                'country_code' => 'NG',
            ]
        );
    }
}
