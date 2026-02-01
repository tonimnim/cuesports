<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Action constants
    public const ACTION_DISPUTE_VIEWED = 'dispute.viewed';
    public const ACTION_DISPUTE_RESOLVED = 'dispute.resolved';
    public const ACTION_USER_VIEWED = 'user.viewed';
    public const ACTION_USER_DEACTIVATED = 'user.deactivated';
    public const ACTION_USER_REACTIVATED = 'user.reactivated';
    public const ACTION_ORGANIZER_VIEWED = 'organizer.viewed';
    public const ACTION_ORGANIZER_ACTIVATED = 'organizer.activated';
    public const ACTION_ORGANIZER_DEACTIVATED = 'organizer.deactivated';
    public const ACTION_ORGANIZER_VERIFIED = 'organizer.verified';
    public const ACTION_NOTE_ADDED = 'note.added';

    // Entity types
    public const ENTITY_MATCH = 'match';
    public const ENTITY_USER = 'user';
    public const ENTITY_ORGANIZER = 'organizer';
    public const ENTITY_TOURNAMENT = 'tournament';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(
        string $action,
        string $entityType,
        int $entityId,
        string $description,
        ?array $metadata = null,
        ?Request $request = null
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_DISPUTE_VIEWED => 'Viewed Dispute',
            self::ACTION_DISPUTE_RESOLVED => 'Resolved Dispute',
            self::ACTION_USER_VIEWED => 'Viewed User',
            self::ACTION_USER_DEACTIVATED => 'Deactivated User',
            self::ACTION_USER_REACTIVATED => 'Reactivated User',
            self::ACTION_ORGANIZER_VIEWED => 'Viewed Organizer',
            self::ACTION_ORGANIZER_ACTIVATED => 'Activated Organizer',
            self::ACTION_ORGANIZER_DEACTIVATED => 'Deactivated Organizer',
            self::ACTION_ORGANIZER_VERIFIED => 'Verified Organizer',
            self::ACTION_NOTE_ADDED => 'Added Note',
            default => ucwords(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            self::ACTION_DISPUTE_RESOLVED => 'green',
            self::ACTION_USER_DEACTIVATED, self::ACTION_ORGANIZER_DEACTIVATED => 'red',
            self::ACTION_USER_REACTIVATED, self::ACTION_ORGANIZER_ACTIVATED => 'green',
            self::ACTION_ORGANIZER_VERIFIED => 'blue',
            default => 'gray',
        };
    }
}
