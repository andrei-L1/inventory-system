<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'draft',               'is_editable' => true],
            ['name' => 'open',                'is_editable' => true],
            ['name' => 'partially_received',  'is_editable' => false],
            ['name' => 'closed',              'is_editable' => false],
            ['name' => 'cancelled',           'is_editable' => false],
        ];

        foreach ($statuses as $status) {
            DB::table('purchase_order_statuses')->updateOrInsert(
                ['name' => $status['name']],
                [...$status, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
