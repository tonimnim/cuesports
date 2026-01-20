<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AgeCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'min_age',
        'max_age',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'is_active' => 'boolean',
    ];

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers

    public function containsAge(int $age): bool
    {
        return $age >= $this->min_age && $age <= $this->max_age;
    }

    public static function forAge(int $age): ?self
    {
        return static::active()
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age)
            ->first();
    }

    public static function forBirthDate(Carbon $birthDate): ?self
    {
        $age = $birthDate->age;
        return static::forAge($age);
    }

    public function getAgeRange(): string
    {
        if ($this->max_age >= 99) {
            return "{$this->min_age}+";
        }

        return "{$this->min_age} - {$this->max_age}";
    }
}
