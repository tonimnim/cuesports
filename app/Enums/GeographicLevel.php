<?php

namespace App\Enums;

enum GeographicLevel: int
{
    case ROOT = 1;      // Africa (Continent)
    case NATIONAL = 2;  // Country (Kenya, Nigeria, etc.)
    case MACRO = 3;     // Region / State / Province
    case MESO = 4;      // County / LGA / District
    case MICRO = 5;     // Constituency / Sub-county
    case NANO = 6;      // Ward / Parish
    case ATOMIC = 7;    // Community / Village (Players register here)

    /**
     * Default label (generic African structure)
     */
    public function label(): string
    {
        return match ($this) {
            self::ROOT => 'Continent',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'District',
            self::MICRO => 'Sub-district',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Get label for specific country by code
     */
    public function labelForCountry(?string $countryCode): string
    {
        if (!$countryCode) {
            return $this->label();
        }

        return match (strtoupper($countryCode)) {
            'KE' => $this->kenyaLabel(),
            'NG' => $this->nigeriaLabel(),
            'ZA' => $this->southAfricaLabel(),
            'GH' => $this->ghanaLabel(),
            'TZ' => $this->tanzaniaLabel(),
            'UG' => $this->ugandaLabel(),
            'EG' => $this->egyptLabel(),
            'ET' => $this->ethiopiaLabel(),
            'RW' => $this->rwandaLabel(),
            'ZW' => $this->zimbabweLabel(),
            'ZM' => $this->zambiaLabel(),
            'BW' => $this->botswanaLabel(),
            'NA' => $this->namibiaLabel(),
            'SN' => $this->senegalLabel(),
            'CI' => $this->ivoryCoastLabel(),
            'CM' => $this->cameroonLabel(),
            default => $this->label(),
        };
    }

    /**
     * Get all level labels for a country
     */
    public static function labelsForCountry(?string $countryCode): array
    {
        $labels = [];
        foreach (self::cases() as $level) {
            $labels[$level->value] = [
                'value' => $level->value,
                'name' => $level->name,
                'label' => $level->labelForCountry($countryCode),
            ];
        }
        return $labels;
    }

    // ==================== Country-Specific Labels ====================

    /**
     * Kenya: Region → County → Constituency → Ward → Community
     */
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

    /**
     * Nigeria: State → LGA → District → Ward → Community
     */
    public function nigeriaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'State',
            self::MESO => 'LGA',
            self::MICRO => 'District',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * South Africa: Province → District Municipality → Local Municipality → Ward → Community
     */
    public function southAfricaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Province',
            self::MESO => 'District Municipality',
            self::MICRO => 'Local Municipality',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Ghana: Region → District → Constituency → Electoral Area → Community
     */
    public function ghanaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'District',
            self::MICRO => 'Constituency',
            self::NANO => 'Electoral Area',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Tanzania: Region → District → Division → Ward → Village
     */
    public function tanzaniaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'District',
            self::MICRO => 'Division',
            self::NANO => 'Ward',
            self::ATOMIC => 'Village',
        };
    }

    /**
     * Uganda: Region → District → County → Sub-county → Parish
     */
    public function ugandaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'District',
            self::MICRO => 'County',
            self::NANO => 'Sub-county',
            self::ATOMIC => 'Parish',
        };
    }

    /**
     * Egypt: Governorate → Markaz → City/District → Shiyakha → Community
     */
    public function egyptLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Governorate',
            self::MESO => 'Markaz',
            self::MICRO => 'City',
            self::NANO => 'Shiyakha',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Ethiopia: Region → Zone → Woreda → Kebele → Community
     */
    public function ethiopiaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'Zone',
            self::MICRO => 'Woreda',
            self::NANO => 'Kebele',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Rwanda: Province → District → Sector → Cell → Village
     */
    public function rwandaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Province',
            self::MESO => 'District',
            self::MICRO => 'Sector',
            self::NANO => 'Cell',
            self::ATOMIC => 'Village',
        };
    }

    /**
     * Zimbabwe: Province → District → Constituency → Ward → Community
     */
    public function zimbabweLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Province',
            self::MESO => 'District',
            self::MICRO => 'Constituency',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Zambia: Province → District → Constituency → Ward → Community
     */
    public function zambiaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Province',
            self::MESO => 'District',
            self::MICRO => 'Constituency',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Botswana: District → Sub-district → Constituency → Ward → Village
     */
    public function botswanaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'District',
            self::MESO => 'Sub-district',
            self::MICRO => 'Constituency',
            self::NANO => 'Ward',
            self::ATOMIC => 'Village',
        };
    }

    /**
     * Namibia: Region → Constituency → Local Authority → Ward → Community
     */
    public function namibiaLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'Constituency',
            self::MICRO => 'Local Authority',
            self::NANO => 'Ward',
            self::ATOMIC => 'Community',
        };
    }

    /**
     * Senegal: Region → Département → Commune → Arrondissement → Village
     */
    public function senegalLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Région',
            self::MESO => 'Département',
            self::MICRO => 'Commune',
            self::NANO => 'Arrondissement',
            self::ATOMIC => 'Village',
        };
    }

    /**
     * Ivory Coast: District → Region → Département → Sous-préfecture → Village
     */
    public function ivoryCoastLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'District',
            self::MESO => 'Région',
            self::MICRO => 'Département',
            self::NANO => 'Sous-préfecture',
            self::ATOMIC => 'Village',
        };
    }

    /**
     * Cameroon: Region → Division → Subdivision → District → Village
     */
    public function cameroonLabel(): string
    {
        return match ($this) {
            self::ROOT => 'Africa',
            self::NATIONAL => 'Country',
            self::MACRO => 'Region',
            self::MESO => 'Division',
            self::MICRO => 'Subdivision',
            self::NANO => 'District',
            self::ATOMIC => 'Village',
        };
    }
}
