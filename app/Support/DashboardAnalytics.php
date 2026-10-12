<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\GuestInquiry;
use App\Models\Service;
use App\Models\SupportRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Numbers for the Super Admin and Admin dashboards. All dates are Y-m-d strings in the business timezone. */
class DashboardAnalytics
{
    private const LIVE = ['pending', 'approved', 'completed', 'no_show'];

    /** @return array<string, mixed> */
    public static function build(): array
    {
        $today = Scheduling::now()->startOfDay();
        $key = fn (CarbonImmutable $day) => $day->format('Y-m-d');

        $rows = Appointment::query()
            ->whereDate('date', '>=', $key($today->subDays(89)))
            ->whereDate('date', '<=', $key($today->addDays(7)))
            ->get(['date', 'status', 'service_id'])
            ->map(fn (Appointment $a) => ['date' => $a->date->format('Y-m-d'), 'status' => $a->status->value, 'service_id' => $a->service_id]);

        $live = $rows->filter(fn (array $r) => in_array($r['status'], self::LIVE, true));
        $between = fn ($set, CarbonImmutable $from, CarbonImmutable $to) => $set->filter(fn (array $r) => $r['date'] >= $key($from) && $r['date'] <= $key($to));

        // Two weeks around today: 6 days back, today, 7 days ahead.
        $daily = [];
        foreach (range(-6, 7) as $offset) {
            $day = $today->addDays($offset);
            $daily[] = [
                'date' => $key($day),
                'label' => $day->format('D, M j'),
                'short' => $day->format('j'),
                'dow' => $day->format('D'),
                'total' => $live->where('date', $key($day))->count(),
                'today' => $offset === 0,
                'future' => $offset > 0,
            ];
        }

        $thisWeek = $between($live, $today->subDays(6), $today)->count();
        $lastWeek = $between($live, $today->subDays(13), $today->subDays(7))->count();

        $last90 = $between($rows, $today->subDays(89), $today);
        $count = fn (string $status) => $last90->where('status', $status)->count();
        $approvedLike = $count('approved') + $count('completed') + $count('no_show');
        $decided = $approvedLike + $count('rejected');
        $attended = $count('completed') + $count('no_show');

        $names = Service::pluck('name', 'id');
        $topServices = $between($live, $today->subDays(29), $today->addDays(7))
            ->groupBy('service_id')
            ->map(fn ($group, $id) => ['name' => $names[$id] ?? 'Deleted service', 'total' => $group->count()])
            ->sortByDesc('total')->values()->take(5)->all();

        $weekdays = array_fill(1, 7, 0);
        foreach ($between($live, $today->subDays(89), $today) as $r) {
            $weekdays[CarbonImmutable::parse($r['date'])->dayOfWeekIso]++;
        }

        $statusCounts = AppointmentStats::counts();
        $total = max(1, $statusCounts['total']);
        $statuses = [];
        foreach (AppointmentStatus::cases() as $status) {
            $statuses[] = [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => $statusCounts[$status->value],
                'percent' => round($statusCounts[$status->value] / $total * 100, 1),
            ];
        }

        return [
            'daily' => $daily,
            'maxDaily' => max(1, max(array_column($daily, 'total'))),
            'thisWeek' => $thisWeek,
            'lastWeek' => $lastWeek,
            'delta' => $lastWeek > 0 ? (int) round(($thisWeek - $lastWeek) / $lastWeek * 100) : null,
            'approvalRate' => $decided > 0 ? (int) round($approvedLike / $decided * 100) : null,
            'noShowRate' => $attended > 0 ? (int) round($count('no_show') / $attended * 100) : null,
            'rating' => AppointmentFeedback::avg('rating'),
            'ratingCount' => AppointmentFeedback::count(),
            'statuses' => $statuses,
            'statusTotal' => $statusCounts['total'],
            'topServices' => $topServices,
            'weekdays' => $weekdays,
            'ops' => [
                'restricted' => User::whereNotNull('booking_restricted_at')->count(),
                'guests' => GuestInquiry::where('status', 'new')->count(),
                'support' => SupportRequest::where('status', 'open')->count(),
            ],
        ];
    }

    /**
     * Rows for the encryption panel: what is stored encrypted, and how many records hold it.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public static function vault(): array
    {
        return [
            ['label' => 'Appointment reasons and notes', 'count' => Appointment::whereNotNull('reason')->orWhereNotNull('admin_note')->count()],
            ['label' => 'Support messages and replies', 'count' => SupportRequest::count()],
            ['label' => 'Guest names, phones and emails', 'count' => GuestInquiry::count()],
        ];
    }
}
