<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'description' => 'Gadgets and hardware', 'is_active' => true],
            ['name' => 'Office Supplies', 'description' => 'Stationery and consumables', 'is_active' => true],
            ['name' => 'Furniture', 'description' => 'Tables, chairs, and office decor', 'is_active' => true],
        ];

        foreach ($categories as $cat) {
            $existing = \DB::table('categories')->where('name', $cat['name'])->first();
            if ($existing) {
                \DB::table('categories')->where('id', $existing->id)->update($cat + ['updated_at' => now()]);
            } else {
                \DB::table('categories')->insert($cat + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
