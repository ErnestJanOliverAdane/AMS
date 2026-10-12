<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends portal notifications. A mail server problem is logged and never
 * stops the request that triggered it.
 */
class Notifier
{
    /**
     * @param  User|iterable<User>  $to
     * @param  array<string, int|string>  $params
     */
    public static function send(User|iterable $to, string $title, string $body, string $route, array $params = [], bool $mail = true): void
    {
        try {
            Notification::send($to, new PortalNotification($title, $body, $route, $params, $mail));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Tells every active Admin and Super Admin about an appointment. */
    public static function staff(string $title, string $body, int $appointmentId, bool $mail = false): void
    {
        self::send(
            User::activeStaff()->get(),
            $title,
            $body,
            'manage.appointments.show',
            ['appointment' => $appointmentId],
            $mail,
        );
    }
}
