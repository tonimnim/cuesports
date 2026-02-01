<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'waived'])
                ->default('pending')
                ->after('status');
            $table->foreignId('payment_id')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_id']);
        });
    }
};
