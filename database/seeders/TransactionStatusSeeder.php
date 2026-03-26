<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'draft',     'is_modifiable' => true],
            ['name' => 'pending',   'is_modifiable' => true],
            ['name' => 'posted',    'is_modifiable' => false],
            ['name' => 'cancelled', 'is_modifiable' => false],
        ];

        foreach ($statuses as $status) {
            DB::table('transaction_statuses')->updateOrInsert(
                ['name' => $status['name']],
                [...$status, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
