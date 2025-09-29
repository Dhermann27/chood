<?php

namespace App\Enums;

/**
 * Represents the sync state of a dog service row with Google Calendar.
 */
enum ServiceSyncStatus: string
{
    /** Awaiting full booking data (e.g., just created) */
    case Pending = 'pending';

    /** Fully populated with time and metadata, ready to be synced */
    case Ready = 'ready';

    /** Calendar event is in sync */
    case Synced = 'synced';

    /** Sync failed due to error, retry later */
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Ready => 'Ready to Sync',
            self::Synced => 'Synced',
            self::Error => 'Error',
        };
    }
}
