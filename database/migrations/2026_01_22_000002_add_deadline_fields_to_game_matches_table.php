<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('confirmation_deadline_at')->nullable();
            $table->string('forfeit_type')->nullable();
            $table->foreignId('no_show_reported_by')
                ->nullable()
                ->constrained('tournament_participants')
                ->nullOnDelete();
            $table->timestamp('no_show_reported_at')->nullable();

            // Add index on deadline_at for query performance
            $table->index('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['deadline_at']);
            $table->dropForeign(['no_show_reported_by']);
            $table->dropColumn([
                'deadline_at',
                'confirmation_deadline_at',
                'forfeit_type',
                'no_show_reported_by',
                'no_show_reported_at',
            ]);
        });
    }
};
