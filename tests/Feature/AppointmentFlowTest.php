<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\Scheduling;
use Carbon\CarbonImmutable;
use Database\Seeders\AppointmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TUESDAY = '2026-10-06';

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['appointments.timezone' => 'Asia/Manila']);
        // Monday 7:00 in Manila.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'Asia/Manila'));

        $this->seed(AppointmentSeeder::class);
        $this->service = Service::where('name', 'WiFi Setup')->firstOrFail(); // 90 minutes
    }

    private function client(): User
    {
        return User::factory()->create(['role' => Role::User]);
    }

    private function staff(Role $role = Role::Admin): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function book(User $user, string $time = '09:00', string $date = self::TUESDAY): Appointment
    {
        $appointment = app(Scheduling::class)->book($user, $this->service, $date, $time, 'Test');
        $this->assertNotNull($appointment, 'booking should succeed');

        return $appointment;
    }

    public function test_slots_skip_the_lunch_break_and_closed_days(): void
    {
        $slots = app(Scheduling::class)->slotsFor($this->service, self::TUESDAY)->pluck('start')->all();

        $this->assertContains('08:00:00', $slots);
        $this->assertNotContains('12:00:00', $slots);
        $this->assertNotContains('12:30:00', $slots);
        $this->assertSame([], app(Scheduling::class)->slotsFor($this->service, '2026-10-11')->all()); // Sunday
    }

    public function test_public_slot_search_works_for_guests(): void
    {
        $this->get(route('slots.search', ['service_id' => $this->service->id, 'date' => self::TUESDAY]))
            ->assertOk()
            ->assertSee('8:00 AM');
    }

    public function test_client_can_request_an_appointment_and_staff_is_notified(): void
    {
        $admin = $this->staff();
        $user = $this->client();

        $this->actingAs($user)->post(route('client.appointments.store'), [
            'service_id' => $this->service->id,
            'date' => self::TUESDAY,
            'start_time' => '09:00',
            'reason' => 'Slow wifi',
        ])->assertRedirect();

        $appointment = Appointment::firstOrFail();
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame($user->id, $appointment->user_id);
        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    public function test_a_full_slot_cannot_be_booked_twice(): void
    {
        $this->book($this->client());

        $this->actingAs($this->client())->post(route('client.appointments.store'), [
            'service_id' => $this->service->id,
            'date' => self::TUESDAY,
            'start_time' => '09:00',
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(1, Appointment::count());
    }

    public function test_admin_can_approve_and_reject(): void
    {
        $admin = $this->staff();
        $approved = $this->book($this->client(), '08:00');
        $rejected = $this->book($this->client(), '10:00');

        $this->actingAs($admin)->post(route('manage.appointments.approve', $approved))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('manage.appointments.reject', $rejected), ['admin_note' => 'Fully booked'])->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Approved, $approved->fresh()->status);
        $this->assertSame(AppointmentStatus::Rejected, $rejected->fresh()->status);
        $this->assertSame(1, $approved->user->unreadNotifications()->count());
    }

    public function test_cancelling_approved_appointments_adds_strikes_and_restricts_the_client(): void
    {
        $user = $this->client();

        foreach (['08:00', '09:30', '10:30'] as $time) {
            $appointment = $this->book($user, $time);
            $appointment->update(['status' => AppointmentStatus::Approved]);

            $this->actingAs($user)->post(route('client.appointments.cancel', $appointment))->assertSessionHasNoErrors();
        }

        $this->assertTrue($user->fresh()->isBookingRestricted());

        $this->actingAs($user)->post(route('client.appointments.store'), [
            'service_id' => $this->service->id,
            'date' => self::TUESDAY,
            'start_time' => '13:00',
        ])->assertSessionHasErrors('booking');
    }

    public function test_clients_cannot_open_other_clients_appointments(): void
    {
        $appointment = $this->book($this->client());

        $this->actingAs($this->client())->get(route('client.appointments.show', $appointment))->assertNotFound();
    }

    public function test_clients_cannot_reach_staff_pages(): void
    {
        $this->actingAs($this->client())->get('/manage/appointments')->assertForbidden();
        $this->actingAs($this->client())->get('/manage/reports')->assertForbidden();
    }

    public function test_staff_pages_render_for_admin_and_super_admin(): void
    {
        $this->book($this->client())->update(['status' => AppointmentStatus::Approved]);

        foreach ([Role::Admin, Role::SuperAdmin] as $role) {
            $this->actingAs($this->staff($role));

            foreach (['manage.appointments.index', 'manage.calendar', 'manage.reports', 'manage.schedule.edit', 'manage.services.index', 'manage.clients.index', 'manage.support.index', 'manage.export.print', 'notifications.index'] as $name) {
                $this->get(route($name))->assertOk();
            }

            $this->get(route('manage.calendar', ['view' => 'week', 'date' => self::TUESDAY]))->assertOk();
            $this->get(route('manage.calendar', ['view' => 'day', 'date' => self::TUESDAY]))->assertOk();
        }
    }

    public function test_dashboards_render_for_every_role(): void
    {
        $this->book($this->client());

        foreach ([[Role::SuperAdmin, 'super-admin.dashboard'], [Role::Admin, 'admin.dashboard'], [Role::User, 'user.dashboard']] as [$role, $route]) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route($route))->assertOk();
        }
    }

    public function test_admin_can_reschedule_and_client_is_notified(): void
    {
        $appointment = $this->book($this->client());

        $this->actingAs($this->staff())->put(route('manage.appointments.reschedule.update', $appointment), [
            'date' => '2026-10-07',
            'start_time' => '13:00',
        ])->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame('2026-10-07', $appointment->date->format('Y-m-d'));
        $this->assertSame('13:00:00', $appointment->start_time);
        $this->assertSame(1, $appointment->user->unreadNotifications()->count());
    }

    public function test_client_reschedule_goes_back_to_pending(): void
    {
        $appointment = $this->book($this->client());
        $appointment->update(['status' => AppointmentStatus::Approved]);

        $this->actingAs($appointment->user)->put(route('client.appointments.reschedule.update', $appointment), [
            'date' => '2026-10-07',
            'start_time' => '09:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
        $this->assertSame(1, $appointment->fresh()->reschedule_count);
    }

    public function test_csv_export_contains_the_appointments_and_guards_formulas(): void
    {
        $user = $this->client();
        $user->update(['name' => '=cmd|calc']);
        $appointment = $this->book($user);

        $response = $this->actingAs($this->staff())->get(route('manage.export.csv'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($appointment->reference, $csv);
        $this->assertStringContainsString("'=cmd|calc", $csv);
    }

    public function test_feedback_is_allowed_only_after_completion_and_only_once(): void
    {
        $appointment = $this->book($this->client());
        $user = $appointment->user;

        $this->actingAs($user)->post(route('client.appointments.feedback', $appointment), ['rating' => 5])
            ->assertSessionHasErrors('appointment');

        $appointment->update(['status' => AppointmentStatus::Completed]);

        $this->actingAs($user)->post(route('client.appointments.feedback', $appointment), ['rating' => 5, 'comment' => 'Great'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('client.appointments.feedback', $appointment), ['rating' => 1])
            ->assertSessionHasErrors('appointment');

        $this->assertSame(1, $appointment->fresh()->feedback()->count());
    }

    public function test_slip_needs_an_approved_appointment(): void
    {
        $appointment = $this->book($this->client());

        $this->actingAs($appointment->user)->get(route('client.appointments.slip', $appointment))->assertRedirect();

        $appointment->update(['status' => AppointmentStatus::Approved]);

        $this->actingAs($appointment->user)->get(route('client.appointments.slip', $appointment))->assertOk()->assertSee($appointment->reference);
    }

    public function test_blocked_dates_remove_all_slots(): void
    {
        BlockedDate::create(['date' => self::TUESDAY, 'reason' => 'Holiday']);

        $this->assertSame(0, app(Scheduling::class)->slotsFor($this->service, self::TUESDAY)->count());
    }

    public function test_admin_can_update_working_hours(): void
    {
        $days = [];
        foreach (range(0, 6) as $weekday) {
            $days[$weekday] = ['opens_at' => '09:00', 'closes_at' => '15:00', 'break_starts_at' => '', 'break_ends_at' => '', 'capacity' => 2];
        }

        $this->actingAs($this->staff())->put(route('manage.schedule.hours'), [
            'days' => $days,
            'open' => [2 => '1'],
        ])->assertSessionHasNoErrors();

        $tuesday = WorkingHour::where('weekday', 2)->firstOrFail();
        $this->assertTrue($tuesday->is_open);
        $this->assertSame('09:00:00', $tuesday->opens_at);
        $this->assertSame(2, $tuesday->capacity);
        $this->assertFalse(WorkingHour::where('weekday', 1)->firstOrFail()->is_open);
    }

    public function test_reminder_command_notifies_once(): void
    {
        $appointment = $this->book($this->client(), '08:00', self::TUESDAY); // starts in ~25h
        $appointment->update(['status' => AppointmentStatus::Approved]);

        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00', 'Asia/Manila')); // 20h before

        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertSame(1, $appointment->user->notifications()->count());
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_support_request_reaches_staff_and_can_be_answered(): void
    {
        $admin = $this->staff();
        $user = $this->client();

        $this->actingAs($user)->post(route('client.support.store'), ['subject' => 'Need evening slot', 'message' => 'I work until 6pm.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $request = \App\Models\SupportRequest::firstOrFail();
        $this->actingAs($admin)->post(route('manage.support.reply', $request), ['admin_reply' => 'We can do 5pm on Fridays.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('closed', $request->fresh()->status);
        $this->assertSame(1, $user->notifications()->count());
    }
}
