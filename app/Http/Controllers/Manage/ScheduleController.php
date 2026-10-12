<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use App\Models\WorkingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Working hours per weekday and blocked dates (holidays, closures). */
class ScheduleController extends Controller
{
    public function edit(): View
    {
        $hours = WorkingHour::orderBy('weekday')->get()->keyBy('weekday');

        return view('manage.schedule.edit', [
            'hours' => $hours,
            'blocked' => BlockedDate::whereDate('date', '>=', now()->subDay()->format('Y-m-d'))->orderBy('date')->get(),
        ]);
    }

    public function hours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'array'],
            'days.*.opens_at' => ['required', 'date_format:H:i'],
            'days.*.closes_at' => ['required', 'date_format:H:i'],
            'days.*.break_starts_at' => ['nullable', 'date_format:H:i'],
            'days.*.break_ends_at' => ['nullable', 'date_format:H:i'],
            'days.*.capacity' => ['required', 'integer', 'between:1,20'],
        ]);

        foreach (range(0, 6) as $weekday) {
            $row = $data['days'][$weekday] ?? null;

            if ($row === null) {
                continue;
            }

            $open = $request->boolean("open.$weekday");

            if ($open && $row['closes_at'] <= $row['opens_at']) {
                return back()->withInput()->withErrors(["days.$weekday.closes_at" => WorkingHour::DAYS[$weekday].': closing time must be after opening time.']);
            }

            if (($row['break_starts_at'] ?? null) xor ($row['break_ends_at'] ?? null)) {
                return back()->withInput()->withErrors(["days.$weekday.break_starts_at" => WorkingHour::DAYS[$weekday].': set both break times or leave both empty.']);
            }

            WorkingHour::updateOrCreate(['weekday' => $weekday], [
                'is_open' => $open,
                'opens_at' => $row['opens_at'].':00',
                'closes_at' => $row['closes_at'].':00',
                'break_starts_at' => ! empty($row['break_starts_at']) ? $row['break_starts_at'].':00' : null,
                'break_ends_at' => ! empty($row['break_ends_at']) ? $row['break_ends_at'].':00' : null,
                'capacity' => $row['capacity'],
            ]);
        }

        return back()->with('status', 'Working hours saved.');
    }

    public function storeBlocked(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:blocked_dates,date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        BlockedDate::create($data);

        return back()->with('status', 'Date blocked.');
    }

    public function destroyBlocked(BlockedDate $blocked): RedirectResponse
    {
        $blocked->delete();

        return back()->with('status', 'Date is open again.');
    }
}
