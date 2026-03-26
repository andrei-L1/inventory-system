<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'receipt',         'code' => 'RCPT', 'affects_inventory' => true,  'is_debit' => true],
            ['name' => 'issue',           'code' => 'ISSU', 'affects_inventory' => true,  'is_debit' => false],
            ['name' => 'transfer',        'code' => 'TRFR', 'affects_inventory' => true,  'is_debit' => false],
            ['name' => 'adjustment',      'code' => 'ADJS', 'affects_inventory' => true,  'is_debit' => true],
            ['name' => 'opening_balance', 'code' => 'OPNB', 'affects_inventory' => true,  'is_debit' => true],
        ];

        foreach ($types as $type) {
            DB::table('transaction_types')->updateOrInsert(
                ['name' => $type['name']],
                [...$type, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
