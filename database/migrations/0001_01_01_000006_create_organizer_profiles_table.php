<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Organization info
            $table->string('organization_name');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();

            // API Access (for future integrations)
            $table->string('api_key', 64)->unique()->nullable();
            $table->string('api_secret')->nullable();
            $table->timestamp('api_key_last_used_at')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Stats
            $table->unsignedInteger('tournaments_hosted')->default(0);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_profiles');
    }
};
