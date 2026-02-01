<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // Notification class name
            $table->string('title');
            $table->text('message');
            $table->string('icon')->nullable(); // Icon name for UI
            $table->string('action_url')->nullable(); // Deep link URL
            $table->string('action_text')->nullable(); // Button text
            $table->json('data')->nullable(); // Additional payload
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
