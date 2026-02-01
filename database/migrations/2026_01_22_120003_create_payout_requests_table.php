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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('payout_method_id')->constrained()->onDelete('restrict');
            $table->bigInteger('amount'); // In smallest unit
            $table->string('currency', 3)->default('KES');
            $table->string('status')->default('pending_review');
            // Status flow: pending_review -> support_confirmed -> admin_approved -> processing -> completed
            // Can also be: rejected (by support or admin)

            // Support review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // Admin approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Rejection (can be by support or admin)
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            // Payment processing
            $table->string('payment_reference')->nullable(); // External reference from payment provider
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_response')->nullable(); // Store provider response

            $table->timestamps();

            $table->index(['organizer_profile_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
