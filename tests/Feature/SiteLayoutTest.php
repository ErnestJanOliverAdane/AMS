<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SiteLayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Main office',
            'width_m' => 30,
            'layers' => ['cctv', 'wifi', 'cabinet'],
        ], $extra);
    }

    public function test_a_client_can_create_a_layout_with_a_pdf_plan(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => Role::User]);

        $response = $this->actingAs($user)->post(route('layouts.store'), $this->payload([
            'plan' => UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf'),
        ]));

        $layout = SiteLayout::firstOrFail();
        $response->assertRedirect(route('layouts.show', ['layout' => $layout, 'auto' => 1]));
        $this->assertSame($user->id, $layout->user_id);
        $this->assertSame(['cctv', 'wifi', 'cabinet'], $layout->layers);
        Storage::assertExists($layout->plan_path);
    }

    public function test_only_pdf_files_and_known_services_are_accepted(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => Role::User]);

        $this->actingAs($user)->post(route('layouts.store'), $this->payload([
            'plan' => UploadedFile::fake()->create('plan.exe', 10, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('plan');

        $this->actingAs($user)->post(route('layouts.store'), $this->payload(['layers' => ['hacking']]))
            ->assertSessionHasErrors('layers.0');

        $this->assertSame(0, SiteLayout::count());
    }

    public function test_the_editor_saves_devices_as_json(): void
    {
        $user = User::factory()->create(['role' => Role::User]);
        $layout = SiteLayout::create(['user_id' => $user->id, 'name' => 'A', 'width_m' => 30, 'layers' => ['cctv'], 'devices' => []]);

        $this->actingAs($user)->putJson(route('layouts.update', $layout), $this->payload([
            'settings' => ['density' => 2, 'ratio' => 0.7, 'camera_range' => 12, 'camera_fov' => 90, 'page' => 1],
            'devices' => [
                ['id' => 'a1', 'type' => 'cam', 'x' => 4.5, 'y' => 3, 'rot' => 90, 'label' => 'CAM-01'],
                ['id' => 'a2', 'type' => 'cab', 'x' => 1.5, 'y' => 18, 'rot' => 0, 'label' => 'MDF'],
            ],
        ]))->assertOk()->assertJson(['saved' => true]);

        $this->assertCount(2, $layout->fresh()->devices);
    }

    public function test_floors_building_outline_and_per_floor_pages_are_saved(): void
    {
        $user = User::factory()->create(['role' => Role::User]);
        $layout = SiteLayout::create(['user_id' => $user->id, 'name' => 'A', 'width_m' => 30, 'layers' => ['cctv'], 'devices' => []]);
        $outline = [[0.1, 0.1], [0.8, 0.1], [0.8, 0.5], [0.4, 0.5], [0.4, 0.9], [0.1, 0.9]];

        $this->actingAs($user)->putJson(route('layouts.update', $layout), $this->payload([
            'settings' => [
                'floors' => 3, 'sens' => 55, 'walls3d' => true, 'outline' => $outline,
                'outlines' => ['2' => [[0.1, 0.1], [0.6, 0.1], [0.6, 0.6]]],
                'floor_pages' => [null, 2, 3],
            ],
            'devices' => [
                ['id' => 'a1', 'type' => 'cam', 'x' => 4.5, 'y' => 3, 'rot' => 90, 'label' => 'CAM-F1-01', 'fl' => 0],
                ['id' => 'a2', 'type' => 'cab', 'x' => 4.5, 'y' => 3, 'rot' => 0, 'label' => 'IDF3', 'fl' => 2],
            ],
        ]))->assertOk();

        $saved = $layout->fresh();
        $this->assertSame(3, $saved->settings['floors']);
        $this->assertCount(6, $saved->settings['outline']);
        $this->assertSame(2, $saved->devices[1]['fl']);
    }

    public function test_bad_floor_counts_and_outlines_are_rejected(): void
    {
        $user = User::factory()->create(['role' => Role::User]);
        $layout = SiteLayout::create(['user_id' => $user->id, 'name' => 'A', 'width_m' => 30, 'layers' => ['cctv'], 'devices' => []]);

        $this->actingAs($user)->putJson(route('layouts.update', $layout), $this->payload(['settings' => ['floors' => 99], 'devices' => []]))->assertStatus(422);
        $this->actingAs($user)->putJson(route('layouts.update', $layout), $this->payload(['settings' => ['outline' => [[1, 2, 3]]], 'devices' => []]))->assertStatus(422);
        $this->actingAs($user)->putJson(route('layouts.update', $layout), $this->payload(['devices' => [['id' => 'x', 'type' => 'cam', 'x' => 1, 'y' => 1, 'fl' => 80]]]))->assertStatus(422);
    }

    public function test_unknown_device_types_are_rejected(): void
    {
        $user = User::factory()->create(['role' => Role::User]);
        $layout = SiteLayout::create(['user_id' => $user->id, 'name' => 'A', 'width_m' => 30, 'layers' => ['cctv'], 'devices' => []]);

        $this->actingAs($user)->putJson(route('layouts.update', $layout), $this->payload([
            'devices' => [['id' => 'x', 'type' => 'bomb', 'x' => 1, 'y' => 1]],
        ]))->assertStatus(422);
    }

    public function test_clients_cannot_open_each_others_layouts_but_staff_can(): void
    {
        Storage::fake();
        $owner = User::factory()->create(['role' => Role::User]);
        $other = User::factory()->create(['role' => Role::User]);
        $admin = User::factory()->create(['role' => Role::Admin]);

        Storage::put('layouts/p.pdf', '%PDF-1.4');
        $layout = SiteLayout::create(['user_id' => $owner->id, 'name' => 'Mine', 'plan_path' => 'layouts/p.pdf', 'width_m' => 30, 'layers' => ['cctv'], 'devices' => []]);

        $this->actingAs($owner)->get(route('layouts.show', $layout))->assertOk()->assertSee('Mine');
        $this->actingAs($owner)->get(route('layouts.plan', $layout))->assertOk();
        $this->actingAs($other)->get(route('layouts.show', $layout))->assertForbidden();
        $this->actingAs($other)->get(route('layouts.plan', $layout))->assertForbidden();
        $this->actingAs($other)->putJson(route('layouts.update', $layout), $this->payload(['devices' => []]))->assertForbidden();
        $this->actingAs($admin)->get(route('layouts.show', $layout))->assertOk();

        $this->actingAs($other)->get(route('layouts.index'))->assertDontSee('Mine');
        $this->actingAs($admin)->get(route('layouts.index'))->assertSee('Mine');
    }

    public function test_deleting_a_layout_removes_its_pdf(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => Role::User]);
        Storage::put('layouts/p.pdf', '%PDF-1.4');
        $layout = SiteLayout::create(['user_id' => $user->id, 'name' => 'A', 'plan_path' => 'layouts/p.pdf', 'width_m' => 30, 'layers' => ['cctv'], 'devices' => []]);

        $this->actingAs($user)->delete(route('layouts.destroy', $layout))->assertRedirect(route('layouts.index'));

        $this->assertSame(0, SiteLayout::count());
        Storage::assertMissing('layouts/p.pdf');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('layouts.index'))->assertRedirect(route('login'));
    }

    public function test_camera_scan_creates_a_complete_layout_and_opens_the_camera(): void
    {
        $user = User::factory()->create(['role' => Role::User]);

        $response = $this->actingAs($user)->post(route('layouts.quick'), ['width_m' => 14]);

        $layout = SiteLayout::firstOrFail();
        $response->assertRedirect(route('layouts.show', ['layout' => $layout, 'auto' => 1, 'camera' => 1]));
        $this->assertSame(array_keys(SiteLayout::LAYERS), $layout->layers);
        $this->assertSame(14.0, $layout->width_m);

        $this->actingAs($user)->post(route('layouts.quick'), ['width_m' => 1])->assertSessionHasErrors('width_m');
    }
}
