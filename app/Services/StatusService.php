<?php

namespace App\Services;

use App\Models\MonitoredService;
use App\Models\ServiceStatusCheck;
use App\Models\ServiceDailyStatus;
use App\Models\ServiceIncident;
use App\Models\ServiceIncidentUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Throwable;

class StatusService
{
    /**
     * Check all active services and record their status.
     */
    public function checkAllServices(): array
    {
        $services = MonitoredService::active()->ordered()->get();
        $results = [];

        foreach ($services as $service) {
            $results[$service->slug] = $this->checkService($service);
        }

        // After checking all services, aggregate daily status
        $this->aggregateDailyStatus();

        return $results;
    }

    /**
     * Check a single service and record the result.
     */
    public function checkService(MonitoredService $service): ServiceStatusCheck
    {
        $startTime = microtime(true);
        $status = 'operational';
        $errorMessage = null;

        try {
            match ($service->check_type) {
                'http' => $this->checkHttp($service),
                'database' => $this->checkDatabase($service),
                'redis' => $this->checkRedis($service),
                'queue' => $this->checkQueue($service),
                'custom' => $this->checkCustom($service),
                default => throw new \Exception("Unknown check type: {$service->check_type}"),
            };
        } catch (Throwable $e) {
            $status = $this->determineStatusFromError($e);
            $errorMessage = $e->getMessage();
        }

        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Determine if response is too slow (degraded)
        if ($status === 'operational' && $responseTime > ($service->timeout_seconds * 1000 * 0.5)) {
            $status = 'degraded';
        }

        $check = ServiceStatusCheck::create([
            'service_id' => $service->id,
            'status' => $status,
            'response_time_ms' => $responseTime,
            'error_message' => $errorMessage,
            'checked_at' => now(),
        ]);

        // Handle incident auto-creation/resolution
        $this->handleIncidentStatus($service, $check);

        return $check;
    }

    /**
     * Check an HTTP endpoint.
     */
    protected function checkHttp(MonitoredService $service): void
    {
        $url = $service->check_endpoint ?? config('app.url') . '/api/health';

        $response = Http::timeout($service->timeout_seconds)
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("HTTP {$response->status()}: {$response->body()}");
        }
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabase(MonitoredService $service): void
    {
        $connection = $service->check_endpoint ?? config('database.default');

        DB::connection($connection)->getPdo();
        DB::connection($connection)->select('SELECT 1');
    }

    /**
     * Check Redis connectivity.
     */
    protected function checkRedis(MonitoredService $service): void
    {
        $connection = $service->check_endpoint ?? 'default';

        Redis::connection($connection)->ping();
    }

    /**
     * Check queue health.
     */
    protected function checkQueue(MonitoredService $service): void
    {
        $connection = $service->check_endpoint ?? config('queue.default');

        // Check if queue connection is available
        $queue = Queue::connection($connection);

        // For Redis queue, check the connection
        if ($connection === 'redis') {
            Redis::connection(config('queue.connections.redis.connection', 'default'))->ping();
        }
    }

    /**
     * Custom check - override in subclass or extend.
     */
    protected function checkCustom(MonitoredService $service): void
    {
        // Custom checks can be added here
        // For now, just pass
    }

    /**
     * Determine status based on error type.
     */
    protected function determineStatusFromError(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'degraded';
        }

        if (str_contains($message, 'connection refused') || str_contains($message, 'could not connect')) {
            return 'major_outage';
        }

        return 'partial_outage';
    }

    /**
     * Handle automatic incident creation and resolution.
     */
    protected function handleIncidentStatus(MonitoredService $service, ServiceStatusCheck $check): void
    {
        $activeIncident = $service->incidents()
            ->active()
            ->latest('started_at')
            ->first();

        if ($check->status !== 'operational') {
            // Service is down - create or update incident
            if (!$activeIncident) {
                $incident = ServiceIncident::create([
                    'title' => "{$service->name} experiencing issues",
                    'status' => 'investigating',
                    'impact' => $this->mapStatusToImpact($check->status),
                    'started_at' => now(),
                ]);

                $incident->services()->attach($service->id);

                ServiceIncidentUpdate::create([
                    'incident_id' => $incident->id,
                    'status' => 'investigating',
                    'message' => "We are investigating issues with {$service->name}. Error: {$check->error_message}",
                    'posted_at' => now(),
                ]);
            }
        } else {
            // Service is operational - resolve any active incident
            if ($activeIncident) {
                // Check if all affected services are operational
                $allOperational = true;
                foreach ($activeIncident->services as $affectedService) {
                    $latestCheck = $affectedService->latestCheck;
                    if ($latestCheck && $latestCheck->status !== 'operational') {
                        $allOperational = false;
                        break;
                    }
                }

                if ($allOperational) {
                    $activeIncident->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                    ]);

                    ServiceIncidentUpdate::create([
                        'incident_id' => $activeIncident->id,
                        'status' => 'resolved',
                        'message' => 'All systems are now operational.',
                        'posted_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Map check status to incident impact.
     */
    protected function mapStatusToImpact(string $status): string
    {
        return match ($status) {
            'degraded' => 'minor',
            'partial_outage' => 'major',
            'major_outage' => 'critical',
            default => 'none',
        };
    }

    /**
     * Aggregate today's checks into daily status.
     */
    public function aggregateDailyStatus(): void
    {
        $today = Carbon::today();
        $services = MonitoredService::active()->get();

        foreach ($services as $service) {
            $todayChecks = $service->statusChecks()
                ->whereDate('checked_at', $today)
                ->get();

            if ($todayChecks->isEmpty()) {
                continue;
            }

            $totalChecks = $todayChecks->count();
            $successfulChecks = $todayChecks->where('status', 'operational')->count();
            $avgResponseTime = (int) $todayChecks->avg('response_time_ms');

            // Determine overall status for the day
            $worstStatus = $todayChecks->contains('status', 'major_outage') ? 'major_outage' :
                ($todayChecks->contains('status', 'partial_outage') ? 'partial_outage' :
                ($todayChecks->contains('status', 'degraded') ? 'degraded' : 'operational'));

            $uptimePercentage = $totalChecks > 0 ? ($successfulChecks / $totalChecks) * 100 : 100;

            ServiceDailyStatus::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'date' => $today,
                ],
                [
                    'status' => $worstStatus,
                    'uptime_percentage' => round($uptimePercentage, 2),
                    'total_checks' => $totalChecks,
                    'successful_checks' => $successfulChecks,
                    'avg_response_time_ms' => $avgResponseTime,
                ]
            );
        }
    }

    /**
     * Get current system status for the API.
     */
    public function getSystemStatus(): array
    {
        $services = MonitoredService::active()
            ->ordered()
            ->with(['latestCheck', 'dailyStatuses' => function ($query) {
                $query->forDays(90)->orderBy('date', 'desc');
            }])
            ->get();

        // Determine overall system status
        $overallStatus = 'operational';
        foreach ($services as $service) {
            $latestCheck = $service->latestCheck;
            if ($latestCheck) {
                if ($latestCheck->status === 'major_outage') {
                    $overallStatus = 'major_outage';
                    break;
                } elseif ($latestCheck->status === 'partial_outage' && $overallStatus !== 'major_outage') {
                    $overallStatus = 'partial_outage';
                } elseif ($latestCheck->status === 'degraded' && $overallStatus === 'operational') {
                    $overallStatus = 'degraded';
                }
            }
        }

        // Get active incidents
        $activeIncidents = ServiceIncident::active()
            ->with(['services', 'updates'])
            ->orderBy('started_at', 'desc')
            ->get();

        // Get recent resolved incidents (last 7 days)
        $recentIncidents = ServiceIncident::resolved()
            ->where('resolved_at', '>=', now()->subDays(7))
            ->with(['services', 'updates'])
            ->orderBy('resolved_at', 'desc')
            ->get();

        return [
            'status' => $overallStatus,
            'status_message' => $this->getStatusMessage($overallStatus),
            'services' => $services->map(function ($service) {
                return [
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'description' => $service->description,
                    'status' => $service->latestCheck?->status ?? 'unknown',
                    'response_time_ms' => $service->latestCheck?->response_time_ms,
                    'last_checked' => $service->latestCheck?->checked_at?->toIso8601String(),
                    'uptime_90_days' => $this->calculateUptimePercentage($service->dailyStatuses),
                    'daily_history' => $service->dailyStatuses->map(function ($day) {
                        return [
                            'date' => $day->date->toDateString(),
                            'status' => $day->status,
                            'uptime' => $day->uptime_percentage,
                        ];
                    }),
                ];
            }),
            'active_incidents' => $activeIncidents->map(function ($incident) {
                return $this->formatIncident($incident);
            }),
            'recent_incidents' => $recentIncidents->map(function ($incident) {
                return $this->formatIncident($incident);
            }),
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate average uptime percentage from daily statuses.
     */
    protected function calculateUptimePercentage($dailyStatuses): float
    {
        if ($dailyStatuses->isEmpty()) {
            return 100.0;
        }

        return round($dailyStatuses->avg('uptime_percentage'), 2);
    }

    /**
     * Get human-readable status message.
     */
    protected function getStatusMessage(string $status): string
    {
        return match ($status) {
            'operational' => 'All Systems Operational',
            'degraded' => 'Degraded Performance',
            'partial_outage' => 'Partial System Outage',
            'major_outage' => 'Major System Outage',
            default => 'Unknown Status',
        };
    }

    /**
     * Format incident for API response.
     */
    protected function formatIncident(ServiceIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => $incident->status,
            'impact' => $incident->impact,
            'started_at' => $incident->started_at->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'affected_services' => $incident->services->pluck('name'),
            'updates' => $incident->updates->map(function ($update) {
                return [
                    'status' => $update->status,
                    'message' => $update->message,
                    'posted_at' => $update->posted_at->toIso8601String(),
                ];
            }),
        ];
    }
}
