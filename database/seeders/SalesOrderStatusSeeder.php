<?php

namespace Database\Seeders;

use App\Models\SalesOrderStatus;
use Illuminate\Database\Seeder;

class SalesOrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Quotation', 'is_editable' => true],
            ['name' => 'Quotation-Sent', 'is_editable' => true],
            ['name' => 'Draft', 'is_editable' => true],
            ['name' => 'Confirmed', 'is_editable' => false],
            ['name' => 'Processing', 'is_editable' => false],
            ['name' => 'Shipped', 'is_editable' => false],
            ['name' => 'Cancelled', 'is_editable' => false],
            ['name' => 'Closed', 'is_editable' => false],
        ];

        foreach ($statuses as $status) {
            SalesOrderStatus::updateOrCreate(['name' => $status['name']], $status);
        }
    }
}
