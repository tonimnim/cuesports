<?php

namespace App\Enums;

enum GeographicLevel: int
{
    case ROOT = 1;      // Africa (Continent)
    case NATIONAL = 2;  // Country (Kenya, Nigeria, etc.)
    case MACRO = 3;     // Region / State
    case MESO = 4;      // County / LGA
    case MICRO = 5;     // Constituency / District
    case NANO = 6;      // Ward / Local Area
    case ATOMIC = 7;    // Community / Village (Players register here)

    public function label(): string
    {
        return match ($this) {
            self::ROOT => 'Continent',
            self::NATIONAL => 'Country',
            self::MACRO => 'Macro Region',
            self::MESO => 'Meso Region',
            self::MICRO => 'Micro Region',
            self::NANO => 'Nano Region',
            self::ATOMIC => 'Community',
        };
    }

    public function kenyaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'County',
            self::MICRO => 'Constituency',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    public function nigeriaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'State',
            self::MESO => 'LGA',
            self::MICRO => 'District',
            self::NANO => 'Local Area',
            self::ATOMIC => 'Community',
        };
    }
}
