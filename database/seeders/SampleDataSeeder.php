<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Fetch needed IDs
            $catId = DB::table('categories')->where('name', 'Electronics')->value('id') ?? DB::table('categories')->first()->id;
            $uomId = DB::table('units_of_measure')->where('abbreviation', 'pcs')->value('id');
            $costMethodId = DB::table('costing_methods')->where('name', 'average')->value('id');
            $whTypeId = DB::table('location_types')->where('name', 'warehouse')->value('id');
            $zoneTypeId = DB::table('location_types')->where('name', 'zone')->value('id');

            // 2. Vendors
            $vendorId = DB::table('vendors')->updateOrInsert(['vendor_code' => 'DEMO-VEND'], [
                'name' => 'Apex Components',
                'email' => 'sales@apex.example',
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $vendorId = DB::table('vendors')->where('vendor_code', 'DEMO-VEND')->value('id');

            // 3. Products
            $products = [
                [
                    'sku' => 'GPU-NV-5090',
                    'name' => 'Thermal GPU G5',
                    'product_code' => 'NV-5090',
                    'category_id' => $catId,
                    'uom_id' => $uomId,
                    'costing_method_id' => $costMethodId,
                    'preferred_vendor_id' => $vendorId,
                    'selling_price' => 2499.00,
                    'average_cost' => 1800.00,
                    'reorder_point' => 10,
                ],
                [
                    'sku' => 'ARC-RE-02',
                    'name' => 'Compact Arc Reactor',
                    'product_code' => 'ARC-02',
                    'category_id' => $catId,
                    'uom_id' => $uomId,
                    'costing_method_id' => $costMethodId,
                    'preferred_vendor_id' => $vendorId,
                    'selling_price' => 150000.00,
                    'average_cost' => 120000.00,
                    'reorder_point' => 2,
                ],
            ];

            foreach ($products as $p) {
                DB::table('products')->updateOrInsert(['sku' => $p['sku']], $p + ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }

            $gpuId = DB::table('products')->where('sku', 'GPU-NV-5090')->value('id');
            $reactorId = DB::table('products')->where('sku', 'ARC-RE-02')->value('id');

            // 4. Locations
            DB::table('locations')->updateOrInsert(['code' => 'DEMO-WH'], [
                'name' => 'Demo Warehouse',
                'location_type_id' => $whTypeId,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $whId = DB::table('locations')->where('code', 'DEMO-WH')->value('id');

            // 5. Inventories
            $inventories = [
                ['product_id' => $gpuId, 'location_id' => $whId, 'quantity_on_hand' => 120, 'average_cost' => 1800.00],
                ['product_id' => $reactorId, 'location_id' => $whId, 'quantity_on_hand' => 5, 'average_cost' => 120000.00],
            ];

            foreach ($inventories as $inv) {
                DB::table('inventories')->updateOrInsert(
                    ['product_id' => $inv['product_id'], 'location_id' => $inv['location_id']],
                    $inv + ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()]
                );
            }

            // 6. Recent Transaction Logs
            $receiptTypeId = DB::table('transaction_types')->where('name', 'receipt')->value('id');
            $postedStatusId = DB::table('transaction_statuses')->where('name', 'posted')->value('id');

            DB::table('transactions')->insert([
                [
                    'reference_number' => 'DEMO-REC-01',
                    'transaction_type_id' => $receiptTypeId,
                    'transaction_status_id' => $postedStatusId,
                    'transaction_date' => Carbon::now()->subDays(5),
                    'to_location_id' => $whId,
                    'vendor_id' => $vendorId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ]);
            $txId = DB::table('transactions')->where('reference_number', 'DEMO-REC-01')->value('id');

            DB::table('transaction_lines')->insert([
                ['transaction_id' => $txId, 'product_id' => $gpuId, 'location_id' => $whId, 'quantity' => 120, 'unit_cost' => 1800.00, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ]);
        });
    }
}
