<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Works out which slots are free and books or moves appointments into them.
 * Dates are plain "Y-m-d" strings and times are minutes since midnight,
 * all read in the business time zone from config/appointments.php.
 */
class Scheduling
{
    /** @var Collection<int, WorkingHour>|null */
    private ?Collection $hours = null;

    /** @var array<int, string>|null */
    private ?array $blocked = null;

    public static function timezone(): string
    {
        return (string) config('appointments.timezone', 'Asia/Manila');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /**
     * Every slot of the day for a service, with how many places are still free.
     *
     * @return Collection<int, array{start: string, end: string, label: string, remaining: int}>
     */
    public function slotsFor(Service $service, string $date, ?int $ignoreAppointmentId = null): Collection
    {
        $now = self::now();

        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $date, self::timezone())->startOfDay();
        } catch (Throwable) {
            return collect();
        }

        if ($day->format('Y-m-d') !== $date) {
            return collect();
        }

        $today = $now->startOfDay();

        if ($day->lt($today) || $day->gt($today->addDays((int) config('appointments.max_days_ahead', 60)))) {
            return collect();
        }

        if (in_array($date, $this->blockedDates(), true)) {
            return collect();
        }

        $hours = $this->workingHours()->get($day->dayOfWeek);

        if (! $hours || ! $hours->is_open) {
            return collect();
        }

        $open = self::minutes($hours->opens_at);
        $close = self::minutes($hours->closes_at);
        $breakStart = $hours->break_starts_at ? self::minutes($hours->break_starts_at) : null;
        $breakEnd = $hours->break_ends_at ? self::minutes($hours->break_ends_at) : null;
        $capacity = max(1, (int) $hours->capacity);
        $duration = max(5, (int) $service->duration_minutes);
        $step = max(5, (int) config('appointments.slot_interval_minutes', 30));
        $earliest = $now->addHours((int) config('appointments.min_notice_hours', 2));

        $taken = Appointment::query()
            ->whereDate('date', $date)
            ->whereIn('status', AppointmentStatus::holdingSlotValues())
            ->when($ignoreAppointmentId, fn ($query) => $query->where('id', '!=', $ignoreAppointmentId))
            ->get(['start_time', 'end_time'])
            ->map(fn (Appointment $appointment) => [self::minutes($appointment->start_time), self::minutes($appointment->end_time)]);

        $slots = collect();

        for ($start = $open; $start + $duration <= $close; $start += $step) {
            $end = $start + $duration;

            if ($breakStart !== null && $breakEnd !== null && $start < $breakEnd && $end > $breakStart) {
                continue;
            }

            $startsAt = $day->addMinutes($start);

            if ($startsAt->lt($earliest)) {
                continue;
            }

            $used = $taken->filter(fn (array $range) => $range[0] < $end && $range[1] > $start)->count();

            $slots->push([
                'start' => self::clock($start),
                'end' => self::clock($end),
                'label' => $startsAt->format('g:i A').' – '.$day->addMinutes($end)->format('g:i A'),
                'remaining' => max(0, $capacity - $used),
            ]);
        }

        return $slots;
    }

    /**
     * The slot that starts at the given time, if it still has room.
     *
     * @return array{start: string, end: string, label: string, remaining: int}|null
     */
    public function findSlot(Service $service, string $date, string $startTime, ?int $ignoreAppointmentId = null): ?array
    {
        $start = substr($startTime, 0, 5).':00';

        return $this->slotsFor($service, $date, $ignoreAppointmentId)
            ->first(fn (array $slot) => $slot['start'] === $start && $slot['remaining'] > 0);
    }

    /**
     * A strip of upcoming days with the number of open slots on each.
     *
     * @return Collection<int, array{date: string, dow: string, day: string, month: string, open: int}>
     */
    public function upcomingAvailability(Service $service, int $days = 14, ?int $ignoreAppointmentId = null): Collection
    {
        $first = self::now()->startOfDay();

        return collect(range(0, $days - 1))->map(function (int $offset) use ($service, $first, $ignoreAppointmentId) {
            $day = $first->addDays($offset);
            $open = $this->slotsFor($service, $day->format('Y-m-d'), $ignoreAppointmentId)
                ->where('remaining', '>', 0)
                ->count();

            return [
                'date' => $day->format('Y-m-d'),
                'dow' => $day->format('D'),
                'day' => $day->format('j'),
                'month' => $day->format('M'),
                'open' => $open,
            ];
        });
    }

    /** Creates a pending appointment, or returns null when the slot was just taken. */
    public function book(User $user, Service $service, string $date, string $startTime, ?string $reason): ?Appointment
    {
        return DB::transaction(function () use ($user, $service, $date, $startTime, $reason) {
            // Locking the hours rows makes two people booking the same slot take turns.
            WorkingHour::query()->lockForUpdate()->get();

            $slot = $this->findSlot($service, $date, $startTime);

            if ($slot === null) {
                return null;
            }

            return Appointment::create([
                'reference' => Appointment::newReference(),
                'user_id' => $user->id,
                'service_id' => $service->id,
                'date' => $date,
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'status' => AppointmentStatus::Pending,
                'reason' => $reason,
            ]);
        });
    }

    /** Moves an appointment to another slot of the same service. Returns false when the slot is gone. */
    public function move(Appointment $appointment, string $date, string $startTime): bool
    {
        return (bool) DB::transaction(function () use ($appointment, $date, $startTime) {
            WorkingHour::query()->lockForUpdate()->get();

            $slot = $this->findSlot($appointment->service, $date, $startTime, $appointment->id);

            if ($slot === null) {
                return false;
            }

            $appointment->forceFill([
                'date' => $date,
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'reschedule_count' => $appointment->reschedule_count + 1,
                'reminder_sent_at' => null,
            ])->save();

            return true;
        });
    }

    /** @return Collection<int, WorkingHour> keyed by weekday */
    private function workingHours(): Collection
    {
        return $this->hours ??= WorkingHour::all()->keyBy('weekday');
    }

    /** @return array<int, string> */
    private function blockedDates(): array
    {
        return $this->blocked ??= BlockedDate::query()
            ->get()
            ->map(fn (BlockedDate $blocked) => Carbon::parse($blocked->date)->format('Y-m-d'))
            ->all();
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
