<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Vendor::updateOrCreate(['code' => 'VEND-001'], [
            'name' => 'Global Supplies Inc.',
            'contact_person' => 'John Doe',
            'email' => 'sales@globalsupplies.com',
            'phone' => '+1-555-0199',
            'address' => '123 Supply Lane',
            'city' => 'New York',
            'country' => 'USA',
            'tax_id' => '12-3456789',
        ]);

        Vendor::updateOrCreate(['code' => 'VEND-002'], [
            'name' => 'Tech Solutions Group',
            'contact_person' => 'Jane Smith',
            'email' => 'partnerships@techsolutions.com',
            'phone' => '+1-555-0211',
            'address' => '456 Innovation Drive',
            'city' => 'San Jose',
            'country' => 'USA',
            'tax_id' => '98-7654321',
        ]);
    }
}
