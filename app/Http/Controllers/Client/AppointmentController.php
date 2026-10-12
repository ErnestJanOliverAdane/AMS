<?php

namespace App\Http\Controllers\Client;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\Service;
use App\Support\Notifier;
use App\Support\Restrictions;
use App\Support\Scheduling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private Scheduling $scheduling)
    {
    }

    /** Upcoming appointments first, then the full history. */
    public function index(Request $request): View
    {
        $now = Scheduling::now();

        $all = Appointment::with('service')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        $upcoming = $all
            ->filter(fn (Appointment $appointment) => $appointment->status->holdsSlot() && $appointment->startsAt()->gte($now))
            ->sortBy(fn (Appointment $appointment) => $appointment->startsAt()->timestamp)
            ->values();

        $history = $all->reject(fn (Appointment $appointment) => $upcoming->contains('id', $appointment->id))->values();

        return view('client.appointments.index', [
            'upcoming' => $upcoming,
            'history' => $history,
        ]);
    }

    public function create(Request $request): View
    {
        $services = Service::active()->orderBy('name')->get();
        $service = $services->firstWhere('id', (int) $request->query('service_id'));
        $date = (string) $request->query('date', '');

        return view('client.appointments.create', [
            'services' => $services,
            'service' => $service,
            'date' => $date,
            'days' => $service ? $this->scheduling->upcomingAvailability($service) : collect(),
            'slots' => $service && $date !== '' ? $this->scheduling->slotsFor($service, $date) : collect(),
            'restricted' => $request->user()->isBookingRestricted(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isBookingRestricted()) {
            return back()->withErrors(['booking' => 'Booking is restricted on your account. Please send a support request.']);
        }

        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $limit = (int) config('appointments.max_active_per_client', 3);

        $active = Appointment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', AppointmentStatus::holdingSlotValues())
            ->whereDate('date', '>=', Scheduling::now()->format('Y-m-d'))
            ->count();

        if ($active >= $limit) {
            return back()->withInput()->withErrors([
                'booking' => "You already have {$limit} active appointments. Cancel one or wait until one is finished.",
            ]);
        }

        $service = Service::findOrFail($data['service_id']);

        $appointment = $this->scheduling->book($user, $service, $data['date'], $data['start_time'], $data['reason'] ?? null);

        if ($appointment === null) {
            return back()->withInput()->withErrors([
                'start_time' => 'That time is no longer available. Please choose another slot.',
            ]);
        }

        Notifier::staff(
            'New appointment request',
            "{$user->name} requested {$service->name} on {$appointment->dateLabel()}, {$appointment->timeLabel()}.",
            $appointment->id,
        );

        return redirect()->route('client.appointments.show', $appointment)
            ->with('status', 'Request sent. We will confirm it soon.');
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $appointment = $this->owned($request, $appointment);

        return view('client.appointments.show', ['appointment' => $appointment] + $this->flags($appointment));
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $appointment = $this->owned($request, $appointment);

        if (! $this->flags($appointment)['canCancel']) {
            return back()->withErrors(['appointment' => 'This appointment can no longer be cancelled.']);
        }

        $wasApproved = $appointment->status === AppointmentStatus::Approved;

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_by' => 'client',
        ]);

        if ($wasApproved) {
            Restrictions::recordStrike($appointment);
        }

        Notifier::staff('Appointment cancelled', "{$appointment->user->name} cancelled {$appointment->reference}.", $appointment->id);

        $message = 'Appointment cancelled.';

        if ($appointment->user->isBookingRestricted()) {
            $message .= ' New requests are now paused because of repeated cancellations.';
        }

        return redirect()->route('client.appointments.index')->with('status', $message);
    }

    public function reschedule(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $appointment = $this->owned($request, $appointment);

        if (! $this->flags($appointment)['canReschedule']) {
            return redirect()->route('client.appointments.show', $appointment)
                ->withErrors(['appointment' => 'This appointment cannot be rescheduled any more.']);
        }

        $service = $appointment->service;
        $date = (string) $request->query('date', '');

        return view('client.appointments.reschedule', [
            'appointment' => $appointment,
            'service' => $service,
            'date' => $date,
            'days' => $this->scheduling->upcomingAvailability($service, 14, $appointment->id),
            'slots' => $date !== '' ? $this->scheduling->slotsFor($service, $date, $appointment->id) : collect(),
        ]);
    }

    public function updateSchedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $appointment = $this->owned($request, $appointment);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        if (! $this->flags($appointment)['canReschedule']) {
            return back()->withErrors(['appointment' => 'This appointment cannot be rescheduled any more.']);
        }

        if (! $this->scheduling->move($appointment, $data['date'], $data['start_time'])) {
            return back()->withErrors(['start_time' => 'That time is no longer available. Please choose another slot.']);
        }

        // A new time needs a new confirmation from the staff.
        $appointment->update(['status' => AppointmentStatus::Pending, 'decided_at' => null]);

        Notifier::staff(
            'Reschedule requested',
            "{$appointment->user->name} asked to move {$appointment->reference} to {$appointment->dateLabel()}, {$appointment->timeLabel()}.",
            $appointment->id,
        );

        return redirect()->route('client.appointments.show', $appointment)
            ->with('status', 'New time sent for approval.');
    }

    public function slip(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $appointment = $this->owned($request, $appointment);

        if (! $this->flags($appointment)['hasSlip']) {
            return redirect()->route('client.appointments.show', $appointment)
                ->withErrors(['appointment' => 'The confirmation slip is available once the appointment is approved.']);
        }

        return view('client.appointments.slip', ['appointment' => $appointment]);
    }

    public function feedback(Request $request, Appointment $appointment): RedirectResponse
    {
        $appointment = $this->owned($request, $appointment);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->flags($appointment)['canFeedback']) {
            return back()->withErrors(['appointment' => 'Feedback can be sent once, after a completed appointment.']);
        }

        AppointmentFeedback::create([
            'appointment_id' => $appointment->id,
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        return back()->with('status', 'Thank you for your feedback.');
    }

    /** A client only ever sees their own appointments. */
    private function owned(Request $request, Appointment $appointment): Appointment
    {
        abort_unless($appointment->user_id === $request->user()->id, 404);

        return $appointment->loadMissing(['service', 'feedback', 'user']);
    }

    /**
     * @return array{canCancel: bool, canReschedule: bool, canFeedback: bool, hasSlip: bool}
     */
    private function flags(Appointment $appointment): array
    {
        $open = $appointment->status->holdsSlot() && $appointment->startsAt()->isFuture();

        return [
            'canCancel' => $open,
            'canReschedule' => $open && $appointment->reschedule_count < (int) config('appointments.max_reschedules', 2),
            'canFeedback' => $appointment->status === AppointmentStatus::Completed && $appointment->feedback === null,
            'hasSlip' => in_array($appointment->status, [AppointmentStatus::Approved, AppointmentStatus::Completed], true),
        ];
    }
}
