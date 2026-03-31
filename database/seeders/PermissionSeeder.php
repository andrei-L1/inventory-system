<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'View Users', 'slug' => 'view-users', 'description' => 'Can view list of users'],
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Can create, edit, and delete users'],

            // Inventory Management
            ['name' => 'View Inventory', 'slug' => 'view-inventory', 'description' => 'Can view stock levels'],
            ['name' => 'Manage Inventory', 'slug' => 'manage-inventory', 'description' => 'Can perform stock adjustments and transfers'],

            // Product Management
            ['name' => 'View Products', 'slug' => 'view-products', 'description' => 'Can view product catalog'],
            ['name' => 'Manage Products', 'slug' => 'manage-products', 'description' => 'Can add, edit, and delete products'],

            // Transaction Management
            ['name' => 'View Transactions', 'slug' => 'view-transactions', 'description' => 'Can view all stock movements'],
            ['name' => 'Post Transactions', 'slug' => 'post-transactions', 'description' => 'Can post pending transactions'],

            // Procurement
            ['name' => 'View Purchase Orders', 'slug' => 'view-purchase-orders', 'description' => 'Can view purchase orders and related receipts'],
            ['name' => 'Manage Purchase Orders', 'slug' => 'manage-purchase-orders', 'description' => 'Can create, edit, and process POs'],

            // Sales
            ['name' => 'Manage Sales Orders', 'slug' => 'manage-sales-orders', 'description' => 'Can create and manage sales orders'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'view-reports', 'description' => 'Can generate and view reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Assign all permissions to Admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(Permission::all());
        }

        // Assign specific permissions to Staff
        $staffRole = Role::where('name', 'staff')->first();
        if ($staffRole) {
            $staffPermissions = Permission::whereIn('slug', [
                'view-users',
                'view-inventory',
                'manage-inventory',
                'view-products',
                'manage-products',
                'view-transactions',
                'post-transactions',
                'view-purchase-orders',
                'manage-purchase-orders',
                'manage-sales-orders',
                'view-reports',
            ])->get();
            $staffRole->permissions()->sync($staffPermissions);
        }

        // Assign limited permissions to User
        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $userPermissions = Permission::whereIn('slug', [
                'view-inventory',
                'view-products',
                'view-purchase-orders',
                'view-reports',
            ])->get();
            $userRole->permissions()->sync($userPermissions);
        }
    }
}
