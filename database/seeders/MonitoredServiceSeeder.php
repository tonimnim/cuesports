<?php

namespace Database\Seeders;

use App\Models\MonitoredService;
use Illuminate\Database\Seeder;

class MonitoredServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'API',
                'slug' => 'api',
                'description' => 'Core API endpoints and services',
                'check_type' => 'database', // Check DB as proxy for API health
                'check_endpoint' => 'pgsql',
                'timeout_seconds' => 10,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Authentication',
                'slug' => 'authentication',
                'description' => 'User authentication and OAuth services',
                'check_type' => 'redis', // Check Redis as proxy for auth health
                'check_endpoint' => 'default',
                'timeout_seconds' => 10,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Database',
                'slug' => 'database',
                'description' => 'PostgreSQL database connections',
                'check_type' => 'database',
                'check_endpoint' => 'pgsql',
                'timeout_seconds' => 5,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Cache',
                'slug' => 'cache',
                'description' => 'Redis cache layer',
                'check_type' => 'redis',
                'check_endpoint' => 'default',
                'timeout_seconds' => 5,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Queue',
                'slug' => 'queue',
                'description' => 'Background job processing',
                'check_type' => 'queue',
                'check_endpoint' => 'redis',
                'timeout_seconds' => 5,
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Email',
                'slug' => 'email',
                'description' => 'Email delivery services',
                'check_type' => 'custom',
                'check_endpoint' => 'mail',
                'timeout_seconds' => 10,
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Real-time',
                'slug' => 'realtime',
                'description' => 'WebSocket and live updates',
                'check_type' => 'custom',
                'check_endpoint' => 'reverb',
                'timeout_seconds' => 10,
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'File Storage',
                'slug' => 'storage',
                'description' => 'Cloud file storage (Cloudinary)',
                'check_type' => 'http',
                'check_endpoint' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg',
                'timeout_seconds' => 10,
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Payments',
                'slug' => 'payments',
                'description' => 'M-Pesa payment processing (Daraja)',
                'check_type' => 'custom', // Manual status - external service
                'check_endpoint' => 'daraja',
                'timeout_seconds' => 15,
                'sort_order' => 9,
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            MonitoredService::updateOrCreate(
                ['slug' => $service['slug']],
                $service
            );
        }
    }
}
