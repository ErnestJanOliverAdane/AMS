<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Scheduling;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Month, week and day calendar of every appointment. */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'view' => ['nullable', 'in:month,week,day'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $view = $data['view'] ?? 'month';
        $today = Scheduling::now()->startOfDay();
        $anchor = isset($data['date']) ? CarbonImmutable::parse($data['date'], Scheduling::timezone())->startOfDay() : $today;

        if ($view === 'day') {
            $start = $anchor;
            $end = $anchor;
            $prev = $anchor->subDay();
            $next = $anchor->addDay();
            $title = $anchor->format('l, F j, Y');
        } elseif ($view === 'week') {
            $start = $anchor->startOfWeek(CarbonInterface::SUNDAY);
            $end = $anchor->endOfWeek(CarbonInterface::SATURDAY)->startOfDay();
            $prev = $anchor->subWeek();
            $next = $anchor->addWeek();
            $title = $start->format('M j').' – '.$end->format('M j, Y');
        } else {
            $start = $anchor->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
            $end = $anchor->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY)->startOfDay();
            $prev = $anchor->subMonthNoOverflow();
            $next = $anchor->addMonthNoOverflow();
            $title = $anchor->format('F Y');
        }

        $showAll = $request->boolean('all');

        $byDate = Appointment::with(['user', 'service'])
            ->whereDate('date', '>=', $start->format('Y-m-d'))
            ->whereDate('date', '<=', $end->format('Y-m-d'))
            ->when(! $showAll, fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled']))
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->date->format('Y-m-d'));

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');

            $days[] = [
                'date' => $key,
                'day' => $day->format('j'),
                'label' => $day->format('D, M j'),
                'in_month' => $day->month === $anchor->month,
                'is_today' => $key === $today->format('Y-m-d'),
                'items' => $byDate->get($key, collect()),
            ];
        }

        return view('manage.calendar.index', [
            'view' => $view,
            'title' => $title,
            'days' => $days,
            'prev' => $prev->format('Y-m-d'),
            'next' => $next->format('Y-m-d'),
            'anchor' => $anchor->format('Y-m-d'),
            'today' => $today->format('Y-m-d'),
            'showAll' => $showAll,
        ]);
    }
}
