<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MonitoredService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'check_type',
        'check_endpoint',
        'timeout_seconds',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'timeout_seconds' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function statusChecks(): HasMany
    {
        return $this->hasMany(ServiceStatusCheck::class, 'service_id');
    }

    public function dailyStatuses(): HasMany
    {
        return $this->hasMany(ServiceDailyStatus::class, 'service_id');
    }

    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(ServiceIncident::class, 'incident_service', 'service_id', 'incident_id');
    }

    public function latestCheck()
    {
        return $this->hasOne(ServiceStatusCheck::class, 'service_id')->latestOfMany('checked_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
