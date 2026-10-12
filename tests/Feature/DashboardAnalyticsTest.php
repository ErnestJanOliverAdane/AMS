<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\DashboardAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_build_on_an_empty_database(): void
    {
        $data = DashboardAnalytics::build();

        $this->assertCount(14, $data['daily']);
        $this->assertNull($data['approvalRate']);
        $this->assertNull($data['delta']);
        $this->assertCount(6, $data['statuses']);
    }

    public function test_each_role_sees_its_dashboard_with_the_encrypted_vault(): void
    {
        foreach ([[Role::SuperAdmin, 'Appointments by visit date'], [Role::Admin, 'Appointments by visit date'], [Role::User, 'Next appointment']] as [$role, $text]) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->followingRedirects()->get('/dashboard')
                ->assertOk()->assertSee($text)->assertSee('Encrypted vault');
        }
    }

    public function test_pages_link_the_app_manifest_for_phone_install(): void
    {
        $this->get('/')->assertOk()->assertSee('manifest.webmanifest', false);
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
    }
}
