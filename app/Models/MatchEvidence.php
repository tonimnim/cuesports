<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvidence extends Model
{
    use HasFactory;

    protected $table = 'match_evidence';

    protected $fillable = [
        'match_id',
        'uploaded_by',
        'file_url',
        'file_type',
        'thumbnail_url',
        'description',
        'evidence_type',
        'uploaded_at',
        'public_id',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public const TYPE_SCORE_PROOF = 'score_proof';
    public const TYPE_DISPUTE_EVIDENCE = 'dispute_evidence';
    public const TYPE_OTHER = 'other';

    public const FILE_TYPE_IMAGE = 'image';
    public const FILE_TYPE_VIDEO = 'video';
    public const FILE_TYPE_DOCUMENT = 'document';

    // New type constants for evidence type enum
    public const EVIDENCE_PHOTO = 'photo';
    public const EVIDENCE_SCREENSHOT = 'screenshot';
    public const EVIDENCE_VIDEO = 'video';

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'uploaded_by');
    }

    /**
     * Alias for uploader() to match the spec.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'uploaded_by');
    }

    public function scopeForMatch($query, int $matchId)
    {
        return $query->where('match_id', $matchId);
    }

    public function scopeScoreProofs($query)
    {
        return $query->where('evidence_type', self::TYPE_SCORE_PROOF);
    }

    public function scopeDisputeEvidence($query)
    {
        return $query->where('evidence_type', self::TYPE_DISPUTE_EVIDENCE);
    }

    public function scopeImages($query)
    {
        return $query->where('file_type', self::FILE_TYPE_IMAGE);
    }

    public function isImage(): bool
    {
        return $this->file_type === self::FILE_TYPE_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->file_type === self::FILE_TYPE_VIDEO;
    }

    public function getDisplayUrl(): string
    {
        return $this->thumbnail_url ?? $this->file_url;
    }

    /**
     * Get the URL (supports both file_url and url columns).
     */
    public function getUrlAttribute($value): ?string
    {
        return $value ?? $this->file_url;
    }

    /**
     * Get the type (supports both file_type and type columns).
     */
    public function getTypeAttribute($value): ?string
    {
        return $value ?? $this->file_type;
    }

    /**
     * Check if evidence is a photo.
     */
    public function isPhoto(): bool
    {
        $type = $this->type ?? $this->file_type;
        return $type === self::EVIDENCE_PHOTO || $type === self::FILE_TYPE_IMAGE;
    }

    /**
     * Check if evidence is a screenshot.
     */
    public function isScreenshot(): bool
    {
        return ($this->type ?? $this->file_type) === self::EVIDENCE_SCREENSHOT;
    }
}
