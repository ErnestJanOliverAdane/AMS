<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentStats
{
    /**
     * Number of appointments for each status, plus a "total".
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $rows = DB::table('appointments')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (AppointmentStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /** @return Collection<int, Appointment> */
    public static function today(): Collection
    {
        return Appointment::with(['user', 'service'])
            ->whereDate('date', Scheduling::now()->format('Y-m-d'))
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Approved->value,
                AppointmentStatus::Completed->value,
            ])
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, Appointment> */
    public static function pending(int $limit = 8): Collection
    {
        return Appointment::with(['user', 'service'])
            ->where('status', AppointmentStatus::Pending->value)
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }
}
