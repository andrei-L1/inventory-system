<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\UnitOfMeasure;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ SAMPLE DATA SEEDER ⚠️
 * Use this ONLY for populating the system with demonstration data.
 * This seeder initializes high-fidelity products and movement history.
 */
class SampleDataSeeder extends Seeder
{
    /**
     * Seed the application with high-fidelity sample inventory data.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Fetch Core Metadata
            $whType = LocationType::where('name', 'warehouse')->first();
            $zoneType = LocationType::where('name', 'zone')->first();

            $avgCost = CostingMethod::where('name', 'average')->first()
                ?? CostingMethod::create(['name' => 'average', 'label' => 'Average Cost', 'is_active' => true]);

            $pcs = UnitOfMeasure::where('name', 'Piece')->first();
            $category = Category::where('name', 'Electronics')->first() ?? Category::first();

            // Note: In some versions we use models, in others just enums
            // But Transaction model has transaction_type_id and transaction_status_id if using lookup tables
            $postedStatusId = DB::table('transaction_statuses')->where('name', 'posted')->value('id');
            $receiptTypeId = DB::table('transaction_types')->where('name', 'receipt')->value('id');
            $transferTypeId = DB::table('transaction_types')->where('name', 'transfer')->value('id');
            $issueTypeId = DB::table('transaction_types')->where('name', 'issue')->value('id');

            // 2. Sample Locations
            $mainWh = Location::updateOrCreate(
                ['code' => 'WH-H01'],
                ['name' => 'Primary Distribution Hub', 'location_type_id' => $whType->id, 'is_active' => true]
            );

            $frontZone = Location::updateOrCreate(
                ['code' => 'ZON-A01'],
                ['name' => 'Display Zone ALPHA', 'location_type_id' => $zoneType->id, 'is_active' => true, 'parent_id' => $mainWh->id]
            );

            // 3. Sample Vendors
            $cyberdyne = Vendor::updateOrCreate(
                ['code' => 'VEND-001'],
                ['name' => 'Cyberdyne Systems', 'email' => 'contact@cyberdyne.tech', 'phone' => '555-0199', 'is_active' => true]
            );

            $stark = Vendor::updateOrCreate(
                ['code' => 'VEND-002'],
                ['name' => 'Stark Industries', 'email' => 'orders@stark.com', 'phone' => '123-IRON', 'is_active' => true]
            );

            // 4. Sample Products
            $gpu = Product::updateOrCreate(
                ['sku' => 'SKU-NV-5090'],
                [
                    'name' => 'Neural Tensor Processor G5',
                    'product_code' => 'NTP-5090',
                    'description' => 'Military-grade GPU optimized for parallel neural computation.',
                    'category_id' => $category->id,
                    'uom_id' => $pcs->id,
                    'costing_method_id' => $avgCost->id,
                    'preferred_vendor_id' => $cyberdyne->id,
                    'selling_price' => 2499.00,
                    'is_active' => true,
                ]
            );

            $reactor = Product::updateOrCreate(
                ['sku' => 'SKU-ST-ARC-01'],
                [
                    'name' => 'Mark II Arc Reactor',
                    'product_code' => 'ARC-II',
                    'description' => 'Zero-point clean energy source.',
                    'category_id' => $category->id,
                    'uom_id' => $pcs->id,
                    'costing_method_id' => $avgCost->id,
                    'preferred_vendor_id' => $stark->id,
                    'selling_price' => 150000.00,
                    'is_active' => true,
                ]
            );

            $this->createSampleTransaction([
                'reference_number' => 'REC-2026-001',
                'transaction_type_id' => $receiptTypeId,
                'vendor_id' => $cyberdyne->id,
                'transaction_status_id' => $postedStatusId,
                'to_location_id' => $mainWh->id,
                'transaction_date' => now()->subDays(10),
                'notes' => 'Inbound procurement from Cyberdyne.',
                'reference_doc' => 'PO-CDB-9422',
                'posted_at' => now()->subDays(10),
                'created_by' => 1,
            ], $gpu->id, $mainWh->id, 50, 1800.00);

            $this->createSampleTransaction([
                'reference_number' => 'XFER-2026-001',
                'transaction_type_id' => $transferTypeId,
                'transaction_status_id' => $postedStatusId,
                'from_location_id' => $mainWh->id,
                'to_location_id' => $frontZone->id,
                'transaction_date' => now()->subDays(5),
                'notes' => 'Stock replenishment.',
                'reference_doc' => 'XFER-INT-01',
                'posted_at' => now()->subDays(5),
                'created_by' => 1,
            ], $gpu->id, $frontZone->id, 10, 1800.00);

            $this->createSampleTransaction([
                'reference_number' => 'ISSUE-2026-001',
                'transaction_type_id' => $issueTypeId,
                'transaction_status_id' => $postedStatusId,
                'from_location_id' => $frontZone->id,
                'transaction_date' => now()->subDays(2),
                'notes' => 'Direct consumer procurement.',
                'reference_doc' => 'SO-CLIENT-098',
                'posted_at' => now()->subDays(2),
                'created_by' => 1,
            ], $gpu->id, $frontZone->id, 2, 1800.00);

            $this->createSampleTransaction([
                'reference_number' => 'REC-2026-002',
                'transaction_type_id' => $receiptTypeId,
                'vendor_id' => $stark->id,
                'transaction_status_id' => $postedStatusId,
                'to_location_id' => $mainWh->id,
                'transaction_date' => now()->subDays(30),
                'notes' => 'New tech arrival from Stark Ind.',
                'reference_doc' => 'PO-STARK-771',
                'posted_at' => now()->subDays(30),
                'created_by' => 1,
            ], $reactor->id, $mainWh->id, 1, 120000.00);
        });
    }

    private function createSampleTransaction(array $data, int $productId, int $locationId, float $qty, float $cost): void
    {
        $transaction = Transaction::updateOrCreate(
            ['reference_number' => $data['reference_number']],
            $data
        );

        TransactionLine::updateOrCreate(
            ['transaction_id' => $transaction->id, 'product_id' => $productId],
            ['quantity' => $qty, 'unit_cost' => $cost, 'location_id' => $locationId]
        );
    }
}
