<?php

namespace Tests\Unit\Models;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created_and_casts_json()
    {
        $user = User::factory()->create();
        $product = Product::create([
            'product_code' => 'P001',
            'name' => 'Test Product',
            'costing_method_id' => 1, // assumes seeded or just int
        ]);

        $audit = AuditLog::create([
            'user_id' => $user->id,
            'event' => 'updated',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'tags' => ['important', 'price-change'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $audit->id,
            'event' => 'updated',
        ]);

        $retrieved = AuditLog::find($audit->id);
        $this->assertIsArray($retrieved->old_values);
        $this->assertEquals('Old Name', $retrieved->old_values['name']);
        $this->assertIsArray($retrieved->tags);
        $this->assertContains('important', $retrieved->tags);
        $this->assertNotNull($retrieved->created_at);
    }

    public function test_activity_log_can_be_created_with_metadata()
    {
        $user = User::factory()->create();

        $activity = ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'LOGIN',
            'description' => 'User logged in',
            'metadata' => ['browser' => 'Chrome', 'device' => 'Desktop'],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $activity->id,
            'action' => 'LOGIN',
        ]);

        $retrieved = ActivityLog::find($activity->id);
        $this->assertIsArray($retrieved->metadata);
        $this->assertEquals('Chrome', $retrieved->metadata['browser']);
        $this->assertNotNull($retrieved->created_at);
    }

    public function test_user_relationships_return_logs()
    {
        $user = User::factory()->create();

        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'test',
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'TEST',
        ]);

        $this->assertCount(1, $user->auditLogs);
        $this->assertCount(1, $user->activityLogs);
        $this->assertInstanceOf(AuditLog::class, $user->auditLogs->first());
        $this->assertInstanceOf(ActivityLog::class, $user->activityLogs->first());
    }
}
