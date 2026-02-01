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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('match_deadline_hours')->nullable()->default(72);
            $table->boolean('auto_confirm_results')->default(true);
            $table->boolean('double_forfeit_on_expiry')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'match_deadline_hours',
                'auto_confirm_results',
                'double_forfeit_on_expiry',
            ]);
        });
    }
};
