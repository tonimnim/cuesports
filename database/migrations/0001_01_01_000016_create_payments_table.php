<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Payment type (polymorphic - can be tournament entry, subscription, etc.)
            $table->string('payable_type'); // App\Models\Tournament, App\Models\Subscription
            $table->unsignedBigInteger('payable_id');

            // Transaction details
            $table->string('reference')->unique(); // Our internal reference
            $table->string('paystack_reference')->nullable(); // Paystack reference
            $table->string('access_code')->nullable(); // Paystack access code
            $table->string('authorization_url')->nullable(); // Payment URL

            // Amount
            $table->integer('amount'); // in smallest currency unit
            $table->string('currency', 3)->default('KES');
            $table->integer('paystack_fees')->nullable(); // Fees charged by Paystack

            // Status
            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'abandoned',
                'refunded',
            ])->default('pending');

            // Payment method details (from Paystack)
            $table->string('channel')->nullable(); // card, bank, ussd, mobile_money
            $table->string('card_type')->nullable(); // visa, mastercard
            $table->string('card_last4')->nullable();
            $table->string('bank')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['user_id', 'status']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
