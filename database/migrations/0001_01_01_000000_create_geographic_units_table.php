<?php

use App\Enums\GeographicLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geographic_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->index();
            $table->unsignedTinyInteger('level');
            $table->string('local_term')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('geographic_units')->onDelete('cascade');
            $table->string('country_code', 3)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['level', 'parent_id']);
            $table->index(['country_code', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geographic_units');
    }
};
