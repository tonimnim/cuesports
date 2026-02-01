<?php

namespace App\Jobs;

use App\Services\StatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckServiceStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Execute the job.
     */
    public function handle(StatusService $statusService): void
    {
        try {
            $results = $statusService->checkAllServices();

            Log::info('Service status check completed', [
                'services_checked' => count($results),
                'results' => collect($results)->map(fn ($check) => [
                    'status' => $check->status,
                    'response_time_ms' => $check->response_time_ms,
                ])->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Service status check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
