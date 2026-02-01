<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNote extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'content',
        'type',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_WARNING = 'warning';
    public const TYPE_BAN_REASON = 'ban_reason';
    public const TYPE_VERIFICATION = 'verification';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeWarnings($query)
    {
        return $query->where('type', self::TYPE_WARNING);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_GENERAL => 'Note',
            self::TYPE_WARNING => 'Warning',
            self::TYPE_BAN_REASON => 'Ban Reason',
            self::TYPE_VERIFICATION => 'Verification',
            default => 'Note',
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            self::TYPE_WARNING => 'orange',
            self::TYPE_BAN_REASON => 'red',
            self::TYPE_VERIFICATION => 'blue',
            default => 'gray',
        };
    }
}
