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
        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'mpesa', 'mtn', 'bank'
            $table->string('provider')->nullable(); // 'paystack', 'direct' (for future)
            $table->string('account_name');
            $table->string('account_number'); // Phone number for mobile money
            $table->string('bank_code')->nullable(); // For bank transfers
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_profile_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_methods');
    }
};
