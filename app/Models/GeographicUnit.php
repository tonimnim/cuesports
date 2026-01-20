<?php

namespace App\Models;

use App\Enums\GeographicLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeographicUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'level',
        'local_term',
        'parent_id',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GeographicUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GeographicUnit::class, 'parent_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(PlayerProfile::class, 'geographic_unit_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLevel($query, GeographicLevel $level)
    {
        return $query->where('level', $level->value);
    }

    public function scopeCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeAtomic($query)
    {
        return $query->where('level', GeographicLevel::ATOMIC->value);
    }

    public function scopeNational($query)
    {
        return $query->where('level', GeographicLevel::NATIONAL->value);
    }

    // Helpers

    public function getLevelEnum(): GeographicLevel
    {
        return GeographicLevel::from($this->level);
    }

    public function isAtomic(): bool
    {
        return $this->level === GeographicLevel::ATOMIC->value;
    }

    public function isNational(): bool
    {
        return $this->level === GeographicLevel::NATIONAL->value;
    }

    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return $ancestors;
    }

    public function getAncestorAtLevel(GeographicLevel $level): ?GeographicUnit
    {
        if ($this->level === $level->value) {
            return $this;
        }

        foreach ($this->getAncestors() as $ancestor) {
            if ($ancestor->level === $level->value) {
                return $ancestor;
            }
        }

        return null;
    }

    public function getCountry(): ?GeographicUnit
    {
        return $this->getAncestorAtLevel(GeographicLevel::NATIONAL);
    }

    public function getFullPath(): string
    {
        $path = [$this->name];
        $current = $this->parent;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get all descendants of this geographic unit (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all descendant IDs including this unit.
     */
    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];
        $this->collectDescendantIds($this->children, $ids);
        return $ids;
    }

    /**
     * Recursively collect descendant IDs.
     */
    protected function collectDescendantIds($children, array &$ids): void
    {
        foreach ($children as $child) {
            $ids[] = $child->id;
            $this->collectDescendantIds($child->children, $ids);
        }
    }

    /**
     * Check if this unit contains another unit (is an ancestor of it).
     */
    public function contains(GeographicUnit $unit): bool
    {
        $current = $unit;

        while ($current) {
            if ($current->id === $this->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Check if this unit is within another unit's scope.
     */
    public function isWithin(GeographicUnit $unit): bool
    {
        return $unit->contains($this);
    }
}
