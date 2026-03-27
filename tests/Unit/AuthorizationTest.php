<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_has_all_permissions(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);

        $allPermissions = Permission::all();
        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $admin->hasPermission($permission->slug),
                "Admin should have '{$permission->slug}' permission"
            );
        }
    }

    public function test_staff_has_limited_permissions(): void
    {
        // Create a staff user
        $staffRole = Role::where('name', 'staff')->first();
        $staff = User::factory()->create([
            'username' => 'staff_user',
            'role_id' => $staffRole->id,
        ]);

        $this->assertTrue($staff->hasPermission('view-inventory'));
        $this->assertTrue($staff->hasPermission('manage-inventory'));
        $this->assertFalse($staff->hasPermission('manage-users'));
    }

    public function test_regular_user_has_minimal_permissions(): void
    {
        // Create a regular user
        $userRole = Role::where('name', 'user')->first();
        $user = User::factory()->create([
            'username' => 'regular_user',
            'role_id' => $userRole->id,
        ]);

        $this->assertTrue($user->hasPermission('view-inventory'));
        $this->assertFalse($user->hasPermission('manage-inventory'));
        $this->assertFalse($user->hasPermission('manage-users'));
    }
}
