<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\GuestInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestInquiryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Maria Santos',
            'phone' => '0917-123-4567',
            'message' => 'Hello, I need CCTV for my shop.',
            'services' => ['CCTV Installation'],
        ], $extra);
    }

    public function test_landing_page_shows_the_get_started_form_to_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Draft your request')->assertSee('Continue as guest');
    }

    public function test_a_guest_can_send_a_message_without_logging_in_and_staff_is_notified(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->post(route('inquiries.store'), $this->payload())->assertRedirect();

        $inquiry = GuestInquiry::firstOrFail();
        $this->assertSame('CCTV Installation', $inquiry->services);
        $this->assertSame('new', $inquiry->status);
        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    public function test_bad_input_is_rejected(): void
    {
        $this->post(route('inquiries.store'), $this->payload(['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->post(route('inquiries.store'), $this->payload(['message' => '']))->assertSessionHasErrors('message');
        $this->assertSame(0, GuestInquiry::count());
    }

    public function test_the_hidden_trap_field_stores_nothing(): void
    {
        $this->post(route('inquiries.store'), $this->payload(['website' => 'http://spam.example']))->assertRedirect();

        $this->assertSame(0, GuestInquiry::count());
    }

    public function test_only_staff_can_read_and_handle_messages(): void
    {
        $inquiry = GuestInquiry::create(['name' => 'A', 'phone' => '0917-111-2222', 'message' => 'Hi']);

        $this->get(route('manage.inquiries.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => Role::User]))->get(route('manage.inquiries.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->get(route('manage.inquiries.index'))->assertOk()->assertSee('0917-111-2222');
        $this->post(route('manage.inquiries.handle', $inquiry))->assertSessionHasNoErrors();

        $this->assertSame('handled', $inquiry->fresh()->status);
    }
}
