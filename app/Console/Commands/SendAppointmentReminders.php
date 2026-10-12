<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Notifier;
use App\Support\Scheduling;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Remind clients about approved appointments that start soon';

    public function handle(): int
    {
        $now = Scheduling::now();
        $limit = $now->addHours((int) config('appointments.reminder_hours_before', 24));
        $sent = 0;

        Appointment::with(['user', 'service'])
            ->where('status', AppointmentStatus::Approved->value)
            ->whereNull('reminder_sent_at')
            ->whereDate('date', '>=', $now->format('Y-m-d'))
            ->whereDate('date', '<=', $limit->format('Y-m-d'))
            ->get()
            ->each(function (Appointment $appointment) use ($now, $limit, &$sent) {
                $start = $appointment->startsAt();

                if ($start->lt($now) || $start->gt($limit)) {
                    return;
                }

                Notifier::send(
                    $appointment->user,
                    'Appointment reminder',
                    "Reminder: {$appointment->service->name} on {$appointment->dateLabel()}, {$appointment->timeLabel()}.",
                    'client.appointments.show',
                    ['appointment' => $appointment->id],
                );

                $appointment->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
