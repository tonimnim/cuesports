<?php

namespace App\Enums;

enum EvidenceType: string
{
    case PHOTO = 'photo';
    case SCREENSHOT = 'screenshot';
    case VIDEO = 'video';

    public function label(): string
    {
        return match($this) {
            self::PHOTO => 'Photo',
            self::SCREENSHOT => 'Screenshot',
            self::VIDEO => 'Video',
        };
    }

    public function maxSizeMB(): int
    {
        return match($this) {
            self::PHOTO => 5,
            self::SCREENSHOT => 5,
            self::VIDEO => 50,
        };
    }
}
