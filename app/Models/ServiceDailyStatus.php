<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDailyStatus extends Model
{
    protected $table = 'service_daily_status';

    protected $fillable = [
        'service_id',
        'date',
        'status',
        'uptime_percentage',
        'total_checks',
        'successful_checks',
        'avg_response_time_ms',
    ];

    protected $casts = [
        'date' => 'date',
        'uptime_percentage' => 'decimal:2',
        'total_checks' => 'integer',
        'successful_checks' => 'integer',
        'avg_response_time_ms' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(MonitoredService::class, 'service_id');
    }

    public function scopeForDays($query, int $days = 90)
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }
}
