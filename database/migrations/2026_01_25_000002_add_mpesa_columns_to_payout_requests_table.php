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
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->string('mpesa_conversation_id')->nullable()->after('paid_at');
            $table->string('mpesa_originator_conversation_id')->nullable()->after('mpesa_conversation_id');
            $table->string('mpesa_transaction_id')->nullable()->after('mpesa_originator_conversation_id');
            $table->string('failure_reason')->nullable()->after('mpesa_transaction_id');

            // Add index for callback lookups
            $table->index('mpesa_conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropIndex(['mpesa_conversation_id']);
            $table->dropColumn([
                'mpesa_conversation_id',
                'mpesa_originator_conversation_id',
                'mpesa_transaction_id',
                'failure_reason',
            ]);
        });
    }
};
