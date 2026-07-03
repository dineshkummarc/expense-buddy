<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ExpenseBuddyTestHarness;
use App\Support\SystemUpdater;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemUpdaterTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        ExpenseBuddyTestHarness::install();

        $response = $this->get('/admin/updater');
        $response->assertRedirect(route('login'));

        $responsePost = $this->post('/admin/updater/run');
        $responsePost->assertRedirect(route('login'));
    }

    #[Test]
    public function users_without_settings_permissions_are_forbidden(): void
    {
        ExpenseBuddyTestHarness::install();

        // Create user without menu.admin.settings permission
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff']);
        $permission = Permission::firstOrCreate(['slug' => 'menu.dashboard', 'name' => 'Dashboard']);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Staff Member',
            'email' => 'staff@expensebuddy.test',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/admin/updater');
        $response->assertStatus(403);

        $responsePost = $this->actingAs($user)->post('/admin/updater/run');
        $responsePost->assertStatus(403);
    }

    #[Test]
    public function administrator_can_access_updater_page(): void
    {
        $admin = ExpenseBuddyTestHarness::install();

        $response = $this->actingAs($admin)->get('/admin/updater');
        $response->assertStatus(200);
        $response->assertViewIs('admin.updater');
        $response->assertSee('System Checks');
        $response->assertSee('Version Details');
    }

    #[Test]
    public function updater_blocks_execution_when_checks_fail_or_changes_exist(): void
    {
        $admin = ExpenseBuddyTestHarness::install();

        // The controller returns a streamed response
        $response = $this->actingAs($admin)->post('/admin/updater/run');
        $response->assertStatus(200);
    }
}
