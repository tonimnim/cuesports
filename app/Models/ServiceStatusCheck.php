<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceStatusCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'status',
        'response_time_ms',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(MonitoredService::class, 'service_id');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('checked_at', '>=', now()->subHours($hours));
    }

    public function isOperational(): bool
    {
        return $this->status === 'operational';
    }
}
