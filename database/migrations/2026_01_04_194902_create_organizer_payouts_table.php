<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->onDelete('cascade');
            $table->string('reference')->unique();
            $table->string('paystack_transfer_code')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->integer('amount'); // In smallest currency unit (cents/kobo)
            $table->string('currency', 3)->default('KES');
            $table->integer('platform_fee')->default(0);
            $table->integer('net_amount'); // Amount after platform fee
            $table->string('status')->default('pending'); // pending, processing, success, failed, reversed
            $table->string('recipient_code')->nullable(); // Paystack transfer recipient
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('tournaments')->nullable(); // Tournament IDs included in this payout
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_profile_id', 'status']);
            $table->index('status');
        });

        // Add balance tracking to organizer profiles
        Schema::table('organizer_profiles', function (Blueprint $table) {
            $table->integer('available_balance')->default(0)->after('tournaments_hosted');
            $table->integer('pending_balance')->default(0)->after('available_balance');
            $table->integer('total_earnings')->default(0)->after('pending_balance');
            $table->integer('total_withdrawn')->default(0)->after('total_earnings');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'pending_balance', 'total_earnings', 'total_withdrawn']);
        });

        Schema::dropIfExists('organizer_payouts');
    }
};
