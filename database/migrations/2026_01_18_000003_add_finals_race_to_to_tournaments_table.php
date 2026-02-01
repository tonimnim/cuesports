<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Rename best_of to race_to for clarity (pool terminology)
            $table->renameColumn('best_of', 'race_to');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            // Add finals_race_to (optional, defaults to race_to if not set)
            $table->unsignedTinyInteger('finals_race_to')->nullable()->after('race_to');

            // Change default for race_to to 3
            $table->unsignedTinyInteger('race_to')->default(3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('finals_race_to');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->renameColumn('race_to', 'best_of');
        });
    }
};
