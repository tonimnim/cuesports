<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('plan_code'); // References config('paystack.plans.{code}')

            // Paystack subscription details
            $table->string('paystack_subscription_code')->nullable();
            $table->string('paystack_email_token')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->string('authorization_code')->nullable(); // For recurring charges

            // Status
            $table->enum('status', [
                'active',
                'cancelled',
                'past_due',
                'expired',
                'pending',
            ])->default('pending');

            // Billing cycle
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // Usage tracking
            $table->integer('tournaments_used')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('plan_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
