<?php

namespace App\Models;

use App\Enums\GeographicLevel;
use Illuminate\Database\Eloquent\Model;

class TournamentLevelSetting extends Model
{
    protected $fillable = [
        'geographic_level',
        'race_to',
        'finals_race_to',
        'confirmation_hours',
    ];

    protected $casts = [
        'geographic_level' => 'integer',
        'race_to' => 'integer',
        'finals_race_to' => 'integer',
        'confirmation_hours' => 'integer',
    ];

    /**
     * Get the geographic level enum.
     */
    public function getLevel(): GeographicLevel
    {
        return GeographicLevel::from($this->geographic_level);
    }

    /**
     * Get settings for a specific geographic level.
     */
    public static function forLevel(GeographicLevel $level): ?self
    {
        return self::where('geographic_level', $level->value)->first();
    }

    /**
     * Get default race_to for a geographic level.
     * Falls back to default values if no setting exists.
     */
    public static function getDefaultRaceTo(GeographicLevel $level): int
    {
        $setting = self::forLevel($level);

        if ($setting) {
            return $setting->race_to;
        }

        // Default values if no admin setting exists
        return match ($level) {
            GeographicLevel::ROOT => 5,      // Continental: Race to 5 (Best of 9)
            GeographicLevel::NATIONAL => 4,  // National: Race to 4 (Best of 7)
            GeographicLevel::MACRO => 3,     // Regional: Race to 3 (Best of 5)
            GeographicLevel::MESO => 3,      // County: Race to 3 (Best of 5)
            GeographicLevel::MICRO => 2,     // Constituency: Race to 2 (Best of 3)
            GeographicLevel::NANO => 2,      // Ward: Race to 2 (Best of 3)
            GeographicLevel::ATOMIC => 2,    // Community: Race to 2 (Best of 3)
        };
    }

    /**
     * Get default finals_race_to for a geographic level.
     * Falls back to race_to + 1 if no setting exists.
     */
    public static function getDefaultFinalsRaceTo(GeographicLevel $level): int
    {
        $setting = self::forLevel($level);

        if ($setting && $setting->finals_race_to) {
            return $setting->finals_race_to;
        }

        // Default: finals are one more than regular matches
        return self::getDefaultRaceTo($level) + 1;
    }

    /**
     * Get default confirmation hours for a geographic level.
     */
    public static function getDefaultConfirmationHours(GeographicLevel $level): int
    {
        $setting = self::forLevel($level);

        if ($setting && $setting->confirmation_hours) {
            return $setting->confirmation_hours;
        }

        // Default: 24 hours for all levels
        return 24;
    }

    /**
     * Get all settings as an array keyed by geographic level value.
     */
    public static function getAllSettings(): array
    {
        $settings = [];

        foreach (GeographicLevel::cases() as $level) {
            $settings[] = [
                'geographic_level' => $level->value,
                'level_label' => $level->label(),
                'race_to' => self::getDefaultRaceTo($level),
                'finals_race_to' => self::getDefaultFinalsRaceTo($level),
                'confirmation_hours' => self::getDefaultConfirmationHours($level),
            ];
        }

        return $settings;
    }
}
