<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Services to monitor
        Schema::create('monitored_services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "API", "Database", "Cache"
            $table->string('slug')->unique(); // e.g., "api", "database", "cache"
            $table->string('description')->nullable();
            $table->string('check_type'); // "http", "database", "redis", "custom"
            $table->string('check_endpoint')->nullable(); // URL or connection name
            $table->integer('timeout_seconds')->default(10);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Status checks (ping results) - one row per check
        Schema::create('service_status_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('monitored_services')->onDelete('cascade');
            $table->enum('status', ['operational', 'degraded', 'partial_outage', 'major_outage']);
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->index(['service_id', 'checked_at']);
        });

        // Daily aggregated status (for the 90-day bar chart)
        Schema::create('service_daily_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('monitored_services')->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['operational', 'degraded', 'partial_outage', 'major_outage']);
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->integer('total_checks')->default(0);
            $table->integer('successful_checks')->default(0);
            $table->integer('avg_response_time_ms')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'date']);
        });

        // Incidents (manual or auto-created)
        Schema::create('service_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('status', ['investigating', 'identified', 'monitoring', 'resolved']);
            $table->enum('impact', ['none', 'minor', 'major', 'critical']);
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Incident updates (timeline)
        Schema::create('service_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('service_incidents')->onDelete('cascade');
            $table->enum('status', ['investigating', 'identified', 'update', 'monitoring', 'resolved', 'postmortem']);
            $table->text('message');
            $table->timestamp('posted_at');
            $table->timestamps();
        });

        // Link incidents to affected services
        Schema::create('incident_service', function (Blueprint $table) {
            $table->foreignId('incident_id')->constrained('service_incidents')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('monitored_services')->onDelete('cascade');
            $table->primary(['incident_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_service');
        Schema::dropIfExists('service_incident_updates');
        Schema::dropIfExists('service_incidents');
        Schema::dropIfExists('service_daily_status');
        Schema::dropIfExists('service_status_checks');
        Schema::dropIfExists('monitored_services');
    }
};
