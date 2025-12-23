<?php

namespace App\Enums;

/**
 * Represents the sync state of a dog service row with Google Calendar.
 */
enum ServiceSyncStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Synced = 'synced';
    case Error = 'error';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Ready => 'Ready to Sync',
            self::Synced => 'Synced',
            self::Error => 'Error',
            self::Archived => 'Archived',
        };
    }
}
