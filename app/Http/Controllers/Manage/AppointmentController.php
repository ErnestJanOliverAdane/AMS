<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\SupportRequest;
use App\Support\AppointmentStats;
use App\Support\Notifier;
use App\Support\Restrictions;
use App\Support\Scheduling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admins and Super Admins review, decide on and move client appointments. */
class AppointmentController extends Controller
{
    public function __construct(private Scheduling $scheduling)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return view('manage.appointments.index', [
            'appointments' => Appointment::with(['user', 'service'])
                ->filter($filters)
                ->orderByDesc('date')
                ->orderBy('start_time')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'counts' => AppointmentStats::counts(),
            'statuses' => AppointmentStatus::cases(),
        ]);
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['user', 'service', 'feedback']);

        return view('manage.appointments.show', [
            'appointment' => $appointment,
            'strikes' => Restrictions::strikes($appointment->user),
            'supportRequests' => SupportRequest::where('appointment_id', $appointment->id)->latest()->get(),
        ]);
    }

    public function approve(Appointment $appointment): RedirectResponse
    {
        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Pending)) {
            return $blocked;
        }

        $appointment->update(['status' => AppointmentStatus::Approved, 'decided_at' => now()]);

        $this->tell($appointment, 'Appointment approved', "Your {$appointment->service->name} appointment on {$appointment->dateLabel()}, {$appointment->timeLabel()} is approved. You can download the confirmation slip.");

        return back()->with('status', 'Appointment approved.');
    }

    public function reject(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Pending)) {
            return $blocked;
        }

        $appointment->update([
            'status' => AppointmentStatus::Rejected,
            'admin_note' => $data['admin_note'] ?? null,
            'decided_at' => now(),
        ]);

        $note = ! empty($data['admin_note']) ? ' Note: '.$data['admin_note'] : '';
        $this->tell($appointment, 'Appointment not approved', "Your {$appointment->service->name} request for {$appointment->dateLabel()} was not approved.{$note}");

        return back()->with('status', 'Appointment rejected.');
    }

    public function complete(Appointment $appointment): RedirectResponse
    {
        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Approved)) {
            return $blocked;
        }

        $appointment->update(['status' => AppointmentStatus::Completed]);

        $this->tell($appointment, 'Appointment completed', "Thank you for visiting. Please rate your {$appointment->service->name} appointment.");

        return back()->with('status', 'Marked as completed.');
    }

    public function noShow(Appointment $appointment): RedirectResponse
    {
        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Approved)) {
            return $blocked;
        }

        $appointment->update(['status' => AppointmentStatus::NoShow]);
        Restrictions::recordStrike($appointment);

        $this->tell($appointment, 'Missed appointment', "You did not attend your {$appointment->service->name} appointment on {$appointment->dateLabel()}.");

        return back()->with('status', 'Marked as no-show.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Pending, AppointmentStatus::Approved)) {
            return $blocked;
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_by' => 'admin',
            'admin_note' => $data['admin_note'] ?? $appointment->admin_note,
        ]);

        $this->tell($appointment, 'Appointment cancelled', "Your {$appointment->service->name} appointment on {$appointment->dateLabel()} was cancelled by our team.");

        return back()->with('status', 'Appointment cancelled.');
    }

    public function rescheduleForm(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Pending, AppointmentStatus::Approved)) {
            return $blocked;
        }

        $service = $appointment->service;
        $date = (string) $request->query('date', '');

        return view('manage.appointments.reschedule', [
            'appointment' => $appointment,
            'service' => $service,
            'date' => $date,
            'days' => $this->scheduling->upcomingAvailability($service, 14, $appointment->id),
            'slots' => $date !== '' ? $this->scheduling->slotsFor($service, $date, $appointment->id) : collect(),
        ]);
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        $appointment->loadMissing(['user', 'service']);

        if ($blocked = $this->requires($appointment, AppointmentStatus::Pending, AppointmentStatus::Approved)) {
            return $blocked;
        }

        if (! $this->scheduling->move($appointment, $data['date'], $data['start_time'])) {
            return back()->withErrors(['start_time' => 'That time is not available. Please choose another slot.']);
        }

        $this->tell($appointment, 'Appointment rescheduled', "Your {$appointment->service->name} appointment was moved to {$appointment->dateLabel()}, {$appointment->timeLabel()}.");

        return redirect()->route('manage.appointments.show', $appointment)
            ->with('status', 'Appointment rescheduled and the client was notified.');
    }

    /** Returns an error redirect unless the appointment is in one of the allowed statuses. */
    private function requires(Appointment $appointment, AppointmentStatus ...$allowed): ?RedirectResponse
    {
        if (in_array($appointment->status, $allowed, true)) {
            return null;
        }

        return back()->withErrors([
            'appointment' => 'This appointment is '.strtolower($appointment->status->label()).' and cannot be changed that way.',
        ]);
    }

    private function tell(Appointment $appointment, string $title, string $body): void
    {
        Notifier::send($appointment->user, $title, $body, 'client.appointments.show', ['appointment' => $appointment->id]);
    }
}
