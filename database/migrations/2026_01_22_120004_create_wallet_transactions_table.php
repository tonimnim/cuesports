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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_wallet_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'credit', 'debit'
            $table->string('source'); // 'tournament_entry', 'payout', 'refund', 'adjustment'
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('currency', 3)->default('KES');
            $table->text('description')->nullable();

            // Reference to related records
            $table->nullableMorphs('reference'); // Can link to Payment, PayoutRequest, etc.

            $table->timestamps();

            $table->index(['organizer_wallet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
