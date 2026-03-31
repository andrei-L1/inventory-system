<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserPermissionTest extends TestCase
{
    public function test_has_permission_is_false_without_role(): void
    {
        $user = new User;
        $user->setRelation('role', null);

        $this->assertFalse($user->hasPermission('manage-inventory'));
    }
}
