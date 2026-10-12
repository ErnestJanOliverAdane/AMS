<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\Service;
use App\Support\Scheduling;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Appointment statistics per day and per month. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $zone = Scheduling::timezone();
        $today = Scheduling::now()->startOfDay();

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], $zone) : $today->subDays(29);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'], $zone) : $today;

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 92) {
            $from = $to->subDays(92);
        }

        $monthsStart = $today->startOfMonth()->subMonths(11);
        $rangeStart = $from->lt($monthsStart) ? $from : $monthsStart;
        $fromKey = $from->format('Y-m-d');
        $toKey = $to->format('Y-m-d');

        $rows = Appointment::query()
            ->whereDate('date', '>=', $rangeStart->format('Y-m-d'))
            ->get(['date', 'status', 'service_id'])
            ->map(fn (Appointment $appointment) => [
                'date' => $appointment->date->format('Y-m-d'),
                'status' => $appointment->status->value,
                'service_id' => $appointment->service_id,
            ]);

        $inRange = $rows->filter(fn (array $row) => $row['date'] >= $fromKey && $row['date'] <= $toKey);

        $perDay = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $perDay[] = ['label' => $day->format('D, M j')] + $this->tally($inRange->where('date', $key));
        }

        $perMonth = [];

        for ($month = $monthsStart; $month->lte($today); $month = $month->addMonthNoOverflow()) {
            $prefix = $month->format('Y-m');
            $perMonth[] = ['label' => $month->format('M Y')] + $this->tally(
                $rows->filter(fn (array $row) => str_starts_with($row['date'], $prefix)),
            );
        }

        $serviceNames = Service::pluck('name', 'id');
        $topServices = $inRange->groupBy('service_id')
            ->map(fn (Collection $group, $serviceId) => ['name' => $serviceNames[$serviceId] ?? 'Deleted service', 'total' => $group->count()])
            ->sortByDesc('total')
            ->values()
            ->take(6);

        return view('manage.reports.index', [
            'from' => $fromKey,
            'to' => $toKey,
            'summary' => $this->tally($inRange),
            'perDay' => $perDay,
            'perMonth' => $perMonth,
            'maxDay' => max(1, collect($perDay)->max('total')),
            'maxMonth' => max(1, collect($perMonth)->max('total')),
            'topServices' => $topServices,
            'averageRating' => AppointmentFeedback::avg('rating'),
            'ratingCount' => AppointmentFeedback::count(),
        ]);
    }

    /**
     * @param  Collection<int, array{date: string, status: string, service_id: int}>  $rows
     * @return array<string, int>
     */
    private function tally(Collection $rows): array
    {
        $counts = ['total' => $rows->count()];

        foreach (AppointmentStatus::cases() as $status) {
            $counts[$status->value] = $rows->where('status', $status->value)->count();
        }

        return $counts;
    }
}
