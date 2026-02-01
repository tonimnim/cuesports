<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatusController extends Controller
{
    public function __construct(
        protected StatusService $statusService
    ) {}

    /**
     * Get the current system status.
     *
     * This endpoint returns the status of all monitored services,
     * including uptime history and any active/recent incidents.
     */
    public function index(): JsonResponse
    {
        // Cache the response for 30 seconds to reduce load
        $status = Cache::remember('system-status', 30, function () {
            return $this->statusService->getSystemStatus();
        });

        return response()->json($status);
    }

    /**
     * Simple health check endpoint for load balancers.
     *
     * Returns 200 if the API is responding, with basic service status.
     */
    public function health(): JsonResponse
    {
        // Quick health check - just verify we can respond
        $healthy = true;
        $services = [];

        // Check database
        try {
            \DB::connection()->getPdo();
            $services['database'] = 'operational';
        } catch (\Throwable $e) {
            $healthy = false;
            $services['database'] = 'down';
        }

        // Check Redis
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $services['redis'] = 'operational';
        } catch (\Throwable $e) {
            $services['redis'] = 'down';
            // Redis being down doesn't necessarily mean unhealthy
        }

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'services' => $services,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
