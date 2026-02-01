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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status');
            $table->string('phone_number')->nullable()->after('payment_method');
            $table->string('mpesa_checkout_request_id')->nullable()->after('phone_number');
            $table->string('mpesa_merchant_request_id')->nullable()->after('mpesa_checkout_request_id');
            $table->string('mpesa_receipt_number')->nullable()->after('mpesa_merchant_request_id');

            // Add index for M-Pesa lookups
            $table->index('mpesa_checkout_request_id');
            $table->index('mpesa_receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['mpesa_checkout_request_id']);
            $table->dropIndex(['mpesa_receipt_number']);
            $table->dropColumn([
                'payment_method',
                'phone_number',
                'mpesa_checkout_request_id',
                'mpesa_merchant_request_id',
                'mpesa_receipt_number',
            ]);
        });
    }
};
