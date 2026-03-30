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
            ['name' => 'purchase_return', 'code' => 'PRET', 'affects_inventory' => true,  'is_debit' => false],
        ];

        foreach ($types as $type) {
            $existing = DB::table('transaction_types')->where('code', $type['code'])->first();
            if ($existing) {
                DB::table('transaction_types')->where('id', $existing->id)->update([...$type, 'updated_at' => now()]);
            } else {
                DB::table('transaction_types')->insert([...$type, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
