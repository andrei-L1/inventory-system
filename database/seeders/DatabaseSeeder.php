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
        // ────────────────────────────────────────────────────────────────────
        // 1. Reference / Lookup Tables  (must run FIRST — domain data depends on them)
        // ────────────────────────────────────────────────────────────────────
        $this->call([
            LocationTypeSeeder::class,        // location_types
            TransactionTypeSeeder::class,     // transaction_types
            TransactionStatusSeeder::class,   // transaction_statuses
            PurchaseOrderStatusSeeder::class, // purchase_order_statuses
            CategorySeeder::class,            // categories
        ]);

        // ────────────────────────────────────────────────────────────────────
        // 2. Roles
        // ────────────────────────────────────────────────────────────────────
        DB::table('roles')->updateOrInsert(['name' => 'admin'], [
            'description' => 'Full system access',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        DB::table('roles')->updateOrInsert(['name' => 'staff'], [
            'description' => 'Can manage inventory and transactions',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $staffRoleId = DB::table('roles')->where('name', 'staff')->value('id');

        DB::table('roles')->updateOrInsert(['name' => 'user'], [
            'description' => 'Regular user with limited access',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // ────────────────────────────────────────────────────────────────────
        // 2.1 Permissions (linked to Roles)
        // ────────────────────────────────────────────────────────────────────
        $this->call(PermissionSeeder::class);

        // ────────────────────────────────────────────────────────────────────
        // 3. Admin User
        // ────────────────────────────────────────────────────────────────────
        DB::table('users')->updateOrInsert(['username' => 'admin'], [
            'role_id' => $adminRoleId,
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // ────────────────────────────────────────────────────────────────────
        // 4. Units of Measure
        // ────────────────────────────────────────────────────────────────────
        $uoms = [
            ['name' => 'Piece',    'abbreviation' => 'pcs', 'is_active' => true],
            ['name' => 'Box',      'abbreviation' => 'bx',  'is_active' => true],
            ['name' => 'Kilogram', 'abbreviation' => 'kg',  'is_active' => true],
        ];

        foreach ($uoms as $uom) {
            DB::table('units_of_measure')->updateOrInsert(['abbreviation' => $uom['abbreviation']], $uom + ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        }

        // ────────────────────────────────────────────────────────────────────
        // 5. Sample Locations  (location_types seeded above — safe to read now)
        // ────────────────────────────────────────────────────────────────────
        $warehouseTypeId = DB::table('location_types')->where('name', 'warehouse')->value('id');
        $zoneTypeId = DB::table('location_types')->where('name', 'zone')->value('id');

        DB::table('locations')->updateOrInsert(['code' => 'WH-A'], [
            'name' => 'Warehouse A',
            'location_type_id' => $warehouseTypeId,
            'address' => '123 Tech Park',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $wh1 = DB::table('locations')->where('code', 'WH-A')->value('id');

        DB::table('locations')->updateOrInsert(['code' => 'WH-A-Z1'], [
            'name' => 'Zone 1 (Storage)',
            'location_type_id' => $zoneTypeId,
            'parent_id' => $wh1,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('locations')->updateOrInsert(['code' => 'WH-A-Z2'], [
            'name' => 'Zone 2 (Dispatch)',
            'location_type_id' => $zoneTypeId,
            'parent_id' => $wh1,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // ────────────────────────────────────────────────────────────────────
        // 6. Vendors
        // ────────────────────────────────────────────────────────────────────
        //        $this->call(VendorSeeder::class);

        // ────────────────────────────────────────────────────────────────────
        // 7. Sample Inventory Data (Products + Stock Movements)
        // ────────────────────────────────────────────────────────────────────
        //        $this->call(SampleDataSeeder::class);
    }
}
