<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('tournament_participants')->cascadeOnDelete();
            $table->string('file_url');
            $table->string('file_type')->default('image'); // image, video, document
            $table->string('thumbnail_url')->nullable();
            $table->text('description')->nullable();
            $table->enum('evidence_type', ['score_proof', 'dispute_evidence', 'other'])->default('score_proof');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['match_id', 'evidence_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_evidence');
    }
};
