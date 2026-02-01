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
        Schema::create('organizer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_profile_id')->unique()->constrained()->onDelete('cascade');
            $table->bigInteger('balance')->default(0); // In smallest currency unit (cents/kobo)
            $table->bigInteger('pending_balance')->default(0); // Pending from unconfirmed payments
            $table->bigInteger('total_earned')->default(0); // Lifetime earnings
            $table->bigInteger('total_withdrawn')->default(0); // Lifetime withdrawals
            $table->string('currency', 3)->default('KES');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_wallets');
    }
};
