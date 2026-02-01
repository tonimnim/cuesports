<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('entry_fee')->default(0)->after('advance_per_group'); // in smallest currency unit
            $table->string('entry_fee_currency', 3)->default('KES')->after('entry_fee');
            $table->boolean('requires_payment')->default(false)->after('entry_fee_currency');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['entry_fee', 'entry_fee_currency', 'requires_payment']);
        });
    }
};
