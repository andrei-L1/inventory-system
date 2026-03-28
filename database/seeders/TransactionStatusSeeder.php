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
            $existing = DB::table('transaction_statuses')->where('name', $status['name'])->first();
            if ($existing) {
                DB::table('transaction_statuses')->where('id', $existing->id)->update([...$status, 'updated_at' => now()]);
            } else {
                DB::table('transaction_statuses')->insert([...$status, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
