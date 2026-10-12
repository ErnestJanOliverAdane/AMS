<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(Role $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        foreach (['/dashboard', '/profile', '/user/dashboard', '/admin/dashboard', '/super-admin/dashboard', '/super-admin/admins', '/manage/users'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public static function roleDashboards(): array
    {
        return [
            'super admin' => [Role::SuperAdmin, 'super-admin.dashboard'],
            'admin' => [Role::Admin, 'admin.dashboard'],
            'user' => [Role::User, 'user.dashboard'],
        ];
    }

    #[DataProvider('roleDashboards')]
    public function test_login_lands_on_the_dashboard_for_the_role(Role $role, string $route): void
    {
        $user = $this->makeUser($role);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($route));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_does_not_sign_in(): void
    {
        $user = $this->makeUser(Role::User);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        $user = $this->makeUser(Role::Admin, ['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_account_deactivated_mid_session_is_signed_out(): void
    {
        $user = $this->makeUser(Role::User, ['is_active' => false]);

        $this->actingAs($user)->get('/user/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public static function accessMatrix(): array
    {
        $rows = [
            // role, URI, expected status
            [Role::User, '/user/dashboard', 200],
            [Role::User, '/profile', 200],
            [Role::User, '/admin/dashboard', 403],
            [Role::User, '/super-admin/dashboard', 403],
            [Role::User, '/super-admin/admins', 403],
            [Role::User, '/manage/users', 403],

            [Role::Admin, '/admin/dashboard', 200],
            [Role::Admin, '/manage/users', 200],
            [Role::Admin, '/profile', 200],
            [Role::Admin, '/user/dashboard', 403],
            [Role::Admin, '/super-admin/dashboard', 403],
            [Role::Admin, '/super-admin/admins', 403],

            [Role::SuperAdmin, '/super-admin/dashboard', 200],
            [Role::SuperAdmin, '/super-admin/admins', 200],
            [Role::SuperAdmin, '/manage/users', 200],
            [Role::SuperAdmin, '/profile', 200],
            [Role::SuperAdmin, '/admin/dashboard', 403],
            [Role::SuperAdmin, '/user/dashboard', 403],
        ];

        $cases = [];
        foreach ($rows as [$role, $uri, $status]) {
            $cases["{$role->value} {$uri}"] = [$role, $uri, $status];
        }

        return $cases;
    }

    #[DataProvider('accessMatrix')]
    public function test_each_role_only_reaches_its_own_pages(Role $role, string $uri, int $status): void
    {
        $this->actingAs($this->makeUser($role))->get($uri)->assertStatus($status);
    }

    public function test_dashboard_route_redirects_each_role_to_its_own_dashboard(): void
    {
        foreach (self::roleDashboards() as [$role, $route]) {
            $this->actingAs($this->makeUser($role))->get('/dashboard')->assertRedirect(route($route));
        }
    }

    public function test_signed_in_users_are_redirected_away_from_the_login_page(): void
    {
        $this->actingAs($this->makeUser(Role::Admin))->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_registration_always_creates_a_plain_user(): void
    {
        $this->post('/register', [
            'name' => 'New Person',
            'email' => 'new@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'super_admin', // must be ignored
        ])->assertRedirect(route('user.dashboard'));

        $this->assertSame(Role::User, User::where('email', 'new@example.test')->firstOrFail()->role);
    }

    public function test_registration_can_be_switched_off(): void
    {
        config(['portal.allow_registration' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_admin_can_create_edit_and_delete_users(): void
    {
        $admin = $this->makeUser(Role::Admin);

        $this->actingAs($admin)->post('/manage/users', [
            'name' => 'Front Desk',
            'email' => 'frontdesk@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ])->assertRedirect(route('manage.users.index'));

        $created = User::where('email', 'frontdesk@example.test')->firstOrFail();
        $this->assertSame(Role::User, $created->role);
        $this->assertTrue($created->is_active);

        $this->actingAs($admin)->put(route('manage.users.update', $created), [
            'name' => 'Front Desk Lead',
            'email' => 'frontdesk@example.test',
            'is_active' => '0',
        ])->assertRedirect(route('manage.users.index'));

        $created->refresh();
        $this->assertSame('Front Desk Lead', $created->name);
        $this->assertFalse($created->is_active);

        $this->actingAs($admin)->delete(route('manage.users.destroy', $created))
            ->assertRedirect(route('manage.users.index'));

        $this->assertModelMissing($created);
    }

    public function test_user_management_never_touches_admins_or_super_admins(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $otherAdmin = $this->makeUser(Role::Admin);
        $superAdmin = $this->makeUser(Role::SuperAdmin);

        foreach ([$otherAdmin, $superAdmin] as $target) {
            $this->actingAs($admin)->get(route('manage.users.edit', $target))->assertNotFound();
            $this->actingAs($admin)->delete(route('manage.users.destroy', $target))->assertNotFound();
        }

        $this->assertModelExists($otherAdmin);
        $this->assertModelExists($superAdmin);
    }

    public function test_super_admin_can_create_and_delete_admins(): void
    {
        $superAdmin = $this->makeUser(Role::SuperAdmin);

        $this->actingAs($superAdmin)->post('/super-admin/admins', [
            'name' => 'Branch Admin',
            'email' => 'branch@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ])->assertRedirect(route('super-admin.admins.index'));

        $created = User::where('email', 'branch@example.test')->firstOrFail();
        $this->assertSame(Role::Admin, $created->role);

        $this->actingAs($superAdmin)->delete(route('super-admin.admins.destroy', $created))
            ->assertRedirect(route('super-admin.admins.index'));

        $this->assertModelMissing($created);
    }

    public function test_admins_cannot_create_admins(): void
    {
        $this->actingAs($this->makeUser(Role::Admin))->post('/super-admin/admins', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.test']);
    }

    public function test_profile_update_changes_name_and_requires_the_current_password_for_a_new_one(): void
    {
        $user = $this->makeUser(Role::User);

        $this->actingAs($user)->put('/profile', ['name' => 'Renamed', 'email' => $user->email])
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $user->refresh()->name);

        $this->actingAs($user)->put('/profile', [
            'name' => 'Renamed',
            'email' => $user->email,
            'current_password' => 'not-my-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/profile', [
            'name' => 'Renamed',
            'email' => $user->email,
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-1'])
            ->assertRedirect(route('user.dashboard'));
    }
}
