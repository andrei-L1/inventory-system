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
            ['name' => 'quotation',         'is_editable' => true],
            ['name' => 'quotation_sent',    'is_editable' => true],
            ['name' => 'confirmed',         'is_editable' => false],
            ['name' => 'picked',             'is_editable' => false],
            ['name' => 'packed',             'is_editable' => false],
            ['name' => 'shipped',            'is_editable' => false],
            ['name' => 'partially_shipped',  'is_editable' => false],
            ['name' => 'cancelled',          'is_editable' => false],
            ['name' => 'closed',             'is_editable' => false],
        ];

        foreach ($statuses as $status) {
            SalesOrderStatus::updateOrCreate(['name' => $status['name']], $status);
        }
    }
}
