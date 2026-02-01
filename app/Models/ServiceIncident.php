<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceIncident extends Model
{
    protected $fillable = [
        'title',
        'status',
        'impact',
        'started_at',
        'resolved_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function updates(): HasMany
    {
        return $this->hasMany(ServiceIncidentUpdate::class, 'incident_id')->orderBy('posted_at', 'desc');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(MonitoredService::class, 'incident_service', 'incident_id', 'service_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
