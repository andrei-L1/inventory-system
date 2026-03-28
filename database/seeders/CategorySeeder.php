<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('categories')->insert([
            ['name' => 'Electronics', 'description' => 'Gadgets and hardware', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Office Supplies', 'description' => 'Stationery and consumables', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Furniture', 'description' => 'Tables, chairs, and office decor', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
