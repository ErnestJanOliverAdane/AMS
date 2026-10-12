<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\GuestInquiry;
use App\Models\Service;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_inquiry_details_are_encrypted_in_the_database_and_readable_in_the_app(): void
    {
        GuestInquiry::create(['name' => 'Maria', 'phone' => '0917-123-4567', 'email' => 'maria@example.com', 'message' => 'Need CCTV at my shop']);

        $raw = DB::table('guest_inquiries')->first();

        foreach (['0917-123-4567', 'maria@example.com', 'Need CCTV'] as $plain) {
            $this->assertStringNotContainsString($plain, $raw->phone.$raw->email.$raw->message);
        }

        $model = GuestInquiry::first();
        $this->assertSame('0917-123-4567', $model->phone);
        $this->assertSame('Need CCTV at my shop', $model->message);
    }

    public function test_appointment_and_support_text_is_encrypted(): void
    {
        $user = User::factory()->create(['role' => Role::User]);
        $service = Service::create(['name' => 'WiFi Setup', 'duration_minutes' => 60, 'is_active' => true]);

        $appointment = Appointment::create([
            'reference' => 'APT-ENC001', 'user_id' => $user->id, 'service_id' => $service->id,
            'date' => '2026-10-20', 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'status' => 'pending', 'reason' => 'My router keeps dropping', 'admin_note' => 'Call first',
        ]);
        $support = SupportRequest::create(['user_id' => $user->id, 'subject' => 'Evening slot', 'message' => 'I work until 6pm', 'status' => 'open']);

        $this->assertStringNotContainsString('router', DB::table('appointments')->value('reason'));
        $this->assertStringNotContainsString('Call first', DB::table('appointments')->value('admin_note'));
        $this->assertStringNotContainsString('6pm', DB::table('support_requests')->value('message'));

        $this->assertSame('My router keeps dropping', $appointment->fresh()->reason);
        $this->assertSame('I work until 6pm', $support->fresh()->message);
    }

    public function test_rows_saved_before_encryption_are_still_readable(): void
    {
        DB::table('guest_inquiries')->insert([
            'name' => 'Old', 'phone' => '0917-000-0000', 'message' => 'Plain text from before', 'status' => 'new',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('Plain text from before', GuestInquiry::first()->message);
        $this->assertSame('0917-000-0000', GuestInquiry::first()->phone);
    }

    public function test_portal_pages_include_the_animated_background(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('pbg-net', false)->assertSee('logo-bg.png', false);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]))
            ->get(route('super-admin.dashboard'))->assertOk()->assertSee('pbg-net', false)->assertSee('Personal details are encrypted');
    }
}
