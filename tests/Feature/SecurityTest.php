<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_inactive_user_is_blocked_from_api(): void
    {
        $role = Role::where('name', 'staff')->firstOrFail();
        $user = User::factory()->inactive()->create(['role_id' => $role->id]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/products')
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'This account has been deactivated.']);
    }
}
