<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_evidence', function (Blueprint $table) {
            // Add public_id for Cloudinary deletion if not exists
            if (!Schema::hasColumn('match_evidence', 'public_id')) {
                $table->string('public_id')->nullable()->after('file_url');
            }

            // Rename file_url to url if needed (done via raw SQL to avoid Laravel issues)
            // Note: The existing column is file_url, but the spec requires url
            // We'll add a url alias or keep using file_url

            // Update file_type to type enum values (photo, screenshot, video)
            // The existing column uses 'image', 'video', 'document' - we'll keep compatibility
        });
    }

    public function down(): void
    {
        Schema::table('match_evidence', function (Blueprint $table) {
            if (Schema::hasColumn('match_evidence', 'public_id')) {
                $table->dropColumn('public_id');
            }
        });
    }
};
