<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'warehouse', 'description' => 'Main storage facility'],
            ['name' => 'zone',      'description' => 'Specific area within a warehouse'],
            ['name' => 'aisle',     'description' => 'Passageway between rows of shelving'],
            ['name' => 'bin',       'description' => 'Smallest storage unit / shelf location'],
        ];

        foreach ($types as $type) {
            $existing = DB::table('location_types')->where('name', $type['name'])->first();
            if ($existing) {
                DB::table('location_types')->where('id', $existing->id)->update([...$type, 'updated_at' => now()]);
            } else {
                DB::table('location_types')->insert([...$type, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
