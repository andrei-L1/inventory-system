<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['name' => 'Cycle Count Variance', 'description' => 'Discrepancy found during routine stock counting.'],
            ['name' => 'Damaged Stock',        'description' => 'Items identified as broken or unusable.'],
            ['name' => 'Expired Goods',       'description' => 'Perishable items that have passed their expiration date.'],
            ['name' => 'Data Entry Error',    'description' => 'Correction for mistake made during previous stock entry.'],
            ['name' => 'Theft / Missing',      'description' => 'Items unaccounted for during audit.'],
            ['name' => 'Return to Vendor',     'description' => 'Stock being removed to be sent back to supplier.'],
        ];

        foreach ($reasons as $reason) {
            DB::table('adjustment_reasons')->updateOrInsert(
                ['name' => $reason['name']],
                array_merge($reason, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
