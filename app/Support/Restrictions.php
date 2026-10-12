<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\User;

/**
 * Counts cancellations of approved appointments and no-shows ("strikes")
 * and restricts booking for clients who collect too many.
 */
class Restrictions
{
    public static function strikes(User $user): int
    {
        $windowDays = (int) config('appointments.restriction.window_days', 90);

        return Appointment::query()
            ->where('user_id', $user->id)
            ->whereNotNull('strike_at')
            ->where('strike_at', '>=', now()->subDays($windowDays))
            ->count();
    }

    public static function recordStrike(Appointment $appointment): void
    {
        $appointment->forceFill(['strike_at' => now()])->save();

        $user = $appointment->user;

        if ($user->isBookingRestricted()) {
            return;
        }

        $strikes = self::strikes($user);
        $threshold = (int) config('appointments.restriction.threshold', 3);
        $windowDays = (int) config('appointments.restriction.window_days', 90);

        if ($strikes >= $threshold) {
            self::restrict($user, "Restricted automatically after {$strikes} cancellations or no-shows in {$windowDays} days.");
        }
    }

    public static function restrict(User $user, ?string $reason): void
    {
        $user->forceFill([
            'booking_restricted_at' => now(),
            'restriction_reason' => $reason,
        ])->save();

        Notifier::send(
            $user,
            'Booking is restricted',
            'New appointment requests are paused on your account. Please send a support request if you need help.',
            'client.support.index',
        );
    }

    public static function lift(User $user): void
    {
        $user->forceFill([
            'booking_restricted_at' => null,
            'restriction_reason' => null,
        ])->save();

        Notifier::send(
            $user,
            'Booking restored',
            'You can request appointments again.',
            'client.appointments.create',
        );
    }
}
