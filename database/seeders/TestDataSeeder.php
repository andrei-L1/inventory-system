<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * MARKS: This seeder is for TEST DATA only.
     * It populates historical cost layers and multi-location distribution.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Resolve Dependencies (Using names as lookup instead of slugs)
            $electronicsCatId = DB::table('categories')->where('name', 'Electronics')->value('id');
            if (! $electronicsCatId) {
                $electronicsCatId = DB::table('categories')->insertGetId([
                    'name' => 'Electronics',
                    'description' => 'Electronic components and devices',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $pcsUomId = DB::table('units_of_measure')->where('abbreviation', 'pcs')->value('id');
            if (! $pcsUomId) {
                $pcsUomId = DB::table('units_of_measure')->insertGetId([
                    'name' => 'Pieces',
                    'abbreviation' => 'pcs',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $fifoId = DB::table('costing_methods')->where('name', 'fifo')->value('id');
            $warehouseTypeId = DB::table('location_types')->where('name', 'warehouse')->value('id');

            $vendorId = DB::table('vendors')->where('code', 'VEND-TEST')->value('id');
            if (! $vendorId) {
                $vendorId = DB::table('vendors')->insertGetId([
                    'code' => 'VEND-TEST',
                    'name' => 'Cyberdyne Systems (Test Vendor)',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Clear Existing Test Records to avoid duplicates from this seeder
            // We use specific SKUs and Codes to avoid nuking real data
            $testSku = 'TEST-NODE-800';
            $testLocA = 'LOC-TEST-SEC-A';
            $testLocB = 'LOC-TEST-SEC-B';

            $existingProductId = DB::table('products')->where('sku', $testSku)->value('id');
            if ($existingProductId) {
                DB::table('inventory_cost_layers')->where('product_id', $existingProductId)->delete();
                DB::table('inventories')->where('product_id', $existingProductId)->delete();
                DB::table('transaction_lines')->where('product_id', $existingProductId)->delete();
                DB::table('products')->where('id', $existingProductId)->delete();
            }

            DB::table('locations')->whereIn('code', [$testLocA, $testLocB])->delete();

            // 3. Create Test Locations
            $locs = [
                ['code' => $testLocA, 'name' => 'Secure Vault Alpha', 'location_type_id' => $warehouseTypeId],
                ['code' => $testLocB, 'name' => 'Secure Vault Beta', 'location_type_id' => $warehouseTypeId],
            ];
            foreach ($locs as $l) {
                DB::table('locations')->insert($l + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $locAId = DB::table('locations')->where('code', $testLocA)->value('id');
            $locBId = DB::table('locations')->where('code', $testLocB)->value('id');

            // 4. Create Test Product (Industrial Grade)
            $productId = DB::table('products')->insertGetId([
                'sku' => $testSku,
                'product_code' => 'NODE-800',
                'name' => 'Autonomous Processing Node T-800',
                'description' => 'High-performance neural processor for industrial automation. Features multi-layer financial tracking.',
                'category_id' => $electronicsCatId,
                'uom_id' => $pcsUomId,
                'costing_method_id' => $fifoId,
                'preferred_vendor_id' => $vendorId,
                'selling_price' => 25000.00,
                'average_cost' => 18500.00,
                'reorder_point' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Create Inventory Cost Layers (Financial Footprint)
            $layers = [
                [
                    'product_id' => $productId,
                    'location_id' => $locAId,
                    'received_qty' => 10,
                    'issued_qty' => 2,
                    'unit_cost' => 18000.00,
                    'receipt_date' => Carbon::now()->subDays(30),
                    'is_exhausted' => false,
                    'created_at' => now()->subDays(30),
                    'updated_at' => now()->subDays(30),
                ],
                [
                    'product_id' => $productId,
                    'location_id' => $locAId,
                    'received_qty' => 5,
                    'issued_qty' => 0,
                    'unit_cost' => 19000.00,
                    'receipt_date' => Carbon::now()->subDays(15),
                    'is_exhausted' => false,
                    'created_at' => now()->subDays(15),
                    'updated_at' => now()->subDays(15),
                ],
                [
                    'product_id' => $productId,
                    'location_id' => $locBId,
                    'received_qty' => 12,
                    'issued_qty' => 0,
                    'unit_cost' => 18500.00,
                    'receipt_date' => Carbon::now()->subDays(10),
                    'is_exhausted' => false,
                    'created_at' => now()->subDays(10),
                    'updated_at' => now()->subDays(10),
                ],
            ];
            DB::table('inventory_cost_layers')->insert($layers);

            // 6. Update Master Inventory (Aggregate Table)
            DB::table('inventories')->insert([
                [
                    'product_id' => $productId,
                    'location_id' => $locAId,
                    'quantity_on_hand' => 13.0,
                    'average_cost' => 18384.62,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_id' => $productId,
                    'location_id' => $locBId,
                    'quantity_on_hand' => 12.0,
                    'average_cost' => 18500.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // 7. Create Historical Transactions
            $receiptTypeId = DB::table('transaction_types')->where('name', 'receipt')->value('id');
            $postedStatusId = DB::table('transaction_statuses')->where('name', 'posted')->value('id');

            $txId = DB::table('transactions')->insertGetId([
                'reference_number' => 'TEST-GRN-001',
                'transaction_type_id' => $receiptTypeId,
                'transaction_status_id' => $postedStatusId,
                'transaction_date' => Carbon::now()->subDays(30),
                'to_location_id' => $locAId,
                'vendor_id' => $vendorId,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ]);

            DB::table('transaction_lines')->insert([
                'transaction_id' => $txId,
                'product_id' => $productId,
                'location_id' => $locAId,
                'quantity' => 10,
                'unit_cost' => 18000.00,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ]);
        });
    }
}
