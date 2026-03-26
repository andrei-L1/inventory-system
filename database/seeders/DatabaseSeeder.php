<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Seed Roles ──────────────────────────────────────────────────────
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'description' => 'Full system access',
            'created_at' => Carbon::now(),
        ]);

        $staffRoleId = DB::table('roles')->insertGetId([
            'name' => 'staff',
            'description' => 'Can manage inventory and transactions',
            'created_at' => Carbon::now(),
        ]);

        $userRoleId = DB::table('roles')->insertGetId([
            'name' => 'user',
            'description' => 'Regular user with limited access',
            'created_at' => Carbon::now(),
        ]);

        // ─── Seed Admin User ─────────────────────────────────────────────────
        DB::table('users')->insert([
            'role_id' => $adminRoleId,
            'username' => 'admin',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'created_at' => Carbon::now(),
        ]);

        // ─── Seed Units of Measure ──────────────────────────────────────────
        DB::table('units_of_measure')->insert([
            ['name' => 'Piece', 'abbreviation' => 'pcs', 'created_at' => Carbon::now()],
            ['name' => 'Box', 'abbreviation' => 'bx', 'created_at' => Carbon::now()],
            ['name' => 'Kilogram', 'abbreviation' => 'kg', 'created_at' => Carbon::now()],
        ]);

        // ─── Seed Initial Locations ──────────────────────────────────────────
        $wh1 = DB::table('locations')->insertGetId([
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'type' => 'warehouse',
            'address' => '123 Tech Park',
            'created_at' => Carbon::now(),
        ]);

        DB::table('locations')->insert([
            [
                'code' => 'WH-A-Z1',
                'name' => 'Zone 1 (Storage)',
                'type' => 'zone',
                'parent_id' => $wh1,
                'created_at' => Carbon::now(),
            ],
            [
                'code' => 'WH-A-Z2',
                'name' => 'Zone 2 (Dispatch)',
                'type' => 'zone',
                'parent_id' => $wh1,
                'created_at' => Carbon::now(),
            ],
        ]);
    }
}
