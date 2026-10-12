<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
        };
    }

    /** Pending and approved appointments occupy a time slot. */
    public function holdsSlot(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }

    /**
     * @return list<string>
     */
    public static function holdingSlotValues(): array
    {
        return [self::Pending->value, self::Approved->value];
    }
}
