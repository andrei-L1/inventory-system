<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\CostingMethod;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\UnitOfMeasure;
use App\Models\UomConversion;
use App\Models\Vendor;
use App\Services\Inventory\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEngineTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;

    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);
        $this->seed(DatabaseSeeder::class);
        $this->seed(VendorSeeder::class);
        $this->vendor = Vendor::where('vendor_code', 'VEND-001')->first();
    }

    public function test_weighted_average_cost_is_recalculated_on_receipt()
    {
        $location = Location::where('code', 'WH-A-Z1')->first();
        $avgMethod = CostingMethod::where('name', 'average')->first();

        $product = Product::create([
            'product_code' => 'P-AVG',
            'name' => 'Avg Product',
            'costing_method_id' => $avgMethod->id,
            'is_active' => true,
        ]);

        $statusId = TransactionStatus::where('name', 'posted')->first()->id;
        $receiptType = TransactionType::where('code', 'RCPT')->first()->id;

        // 1. Receive 10 @ $10.00
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'R-001',
                'transaction_type_id' => $receiptType,
                'transaction_status_id' => $statusId,
                'transaction_date' => now(),
                'vendor_id' => $this->vendor->id,
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => 10,
                    'unit_cost' => 10.00,
                ],
            ],
        ]);

        $inventory = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(10.00, (float) $inventory->average_cost);

        // 2. Receive 10 more @ $20.00
        // Result should be: (10*10 + 10*20) / 20 = 300 / 20 = 15.00
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'R-002',
                'transaction_type_id' => $receiptType,
                'transaction_status_id' => $statusId,
                'transaction_date' => now(),
                'vendor_id' => $this->vendor->id,
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => 10,
                    'unit_cost' => 20.00,
                ],
            ],
        ]);

        $inventory->refresh();
        $product->refresh();

        $this->assertEquals(20, (float) $inventory->quantity_on_hand);
        $this->assertEquals(15.00, (float) $inventory->average_cost, 'Location Average Cost failed');
        $this->assertEquals(15.00, (float) $product->average_cost, 'Product Catalog Average Cost failed');
    }

    public function test_it_throws_insufficient_stock_exception_on_overconsumption()
    {
        $location = Location::where('code', 'WH-A-Z1')->first();
        $product = Product::create([
            'product_code' => 'P-OOS',
            'name' => 'OOS Product',
            'costing_method_id' => CostingMethod::where('name', 'fifo')->first()->id,
            'is_active' => true,
        ]);

        $this->expectException(InsufficientStockException::class);

        // Try to issue 10 units when none exist
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'R-FAIL',
                'transaction_type_id' => TransactionType::where('code', 'ISSU')->first()->id,
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->first()->id,
                'transaction_date' => now(),
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => -10,
                    'unit_cost' => 0,
                ],
            ],
        ]);
    }

    public function test_it_handles_atomic_transfers_between_locations()
    {
        $locA = Location::where('code', 'WH-A-Z1')->first();
        $locB = Location::where('code', 'WH-A-Z2')->first();
        $product = Product::create([
            'product_code' => 'P-TRFR',
            'name' => 'Transfer Product',
            'costing_method_id' => CostingMethod::where('name', 'fifo')->first()->id,
            'is_active' => true,
        ]);

        $statusId = TransactionStatus::where('name', 'posted')->first()->id;
        $transferType = TransactionType::where('code', 'TRFR')->first()->id;

        // 1. Receive 100 @ $1.00 at Location A
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'R-A',
                'transaction_type_id' => TransactionType::where('code', 'RCPT')->first()->id,
                'transaction_status_id' => $statusId,
                'transaction_date' => now(),
                'vendor_id' => $this->vendor->id,
            ],
            'lines' => [['product_id' => $product->id, 'location_id' => $locA->id, 'quantity' => 100, 'unit_cost' => 1.00]],
        ]);

        // 2. Transfer 40 from Location A to Location B
        $this->service->recordTransfer([
            'header' => [
                'reference_number' => 'TRX-TRFR',
                'transaction_type_id' => $transferType,
                'transaction_status_id' => $statusId,
                'transaction_date' => now(),
                'from_location_id' => $locA->id,
                'to_location_id' => $locB->id,
            ],
            'from_location_id' => $locA->id,
            'to_location_id' => $locB->id,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 40,
                    'unit_cost' => 1.00, // Move at the current cost
                ],
            ],
        ]);

        $invA = Inventory::where('product_id', $product->id)->where('location_id', $locA->id)->first();
        $invB = Inventory::where('product_id', $product->id)->where('location_id', $locB->id)->first();

        $this->assertEquals(60, (float) $invA->quantity_on_hand);
        $this->assertEquals(40, (float) $invB->quantity_on_hand);
    }

    public function test_concurrency_prevents_overselling()
    {
        $location = Location::where('code', 'WH-A-Z1')->first();
        $product = Product::create([
            'product_code' => 'P-CONCUR',
            'name' => 'Concurrency Product',
            'costing_method_id' => CostingMethod::where('name', 'fifo')->first()->id,
            'is_active' => true,
        ]);

        $statusId = TransactionStatus::where('name', 'posted')->first()->id;
        $receiptType = TransactionType::where('code', 'RCPT')->first()->id;
        $issueType = TransactionType::where('code', 'ISSU')->first()->id;

        // 1. Receive exactly 1 unit
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'R-CONC',
                'transaction_type_id' => $receiptType,
                'transaction_status_id' => $statusId,
                'transaction_date' => now(),
                'vendor_id' => $this->vendor->id,
            ],
            'lines' => [['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => 1, 'unit_cost' => 10.00]],
        ]);

        // 2. Simulate 10 sequential issues of that 1 unit (simulating concurrency serialization)
        // With lockForUpdate(), true concurrent DB requests would serialize here.
        // We verify that only 1 succeeds and 9 throw InsufficientStockException.
        $successCount = 0;
        $exceptionCount = 0;

        for ($i = 0; $i < 10; $i++) {
            try {
                $this->service->recordMovement([
                    'header' => [
                        'reference_number' => 'I-CONC-'.$i,
                        'transaction_type_id' => $issueType,
                        'transaction_status_id' => $statusId,
                        'transaction_date' => now(),
                    ],
                    'lines' => [
                        [
                            'product_id' => $product->id,
                            'location_id' => $location->id,
                            'quantity' => -1,
                            'unit_cost' => 0,
                        ],
                    ],
                ]);
                $successCount++;
            } catch (InsufficientStockException $e) {
                $exceptionCount++;
            }
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(9, $exceptionCount);
    }

    public function test_uom_conversion_on_receipt_and_issue()
    {
        $location = Location::where('code', 'WH-A-Z1')->first();

        // 1. Setup UOMs and Conversion
        $piecesUom = UnitOfMeasure::create(['name' => 'Pieces Test', 'abbreviation' => 'pcst']);
        $boxUom = UnitOfMeasure::create(['name' => 'Box Test', 'abbreviation' => 'boxt']);

        UomConversion::create([
            'from_uom_id' => $boxUom->id,
            'to_uom_id' => $piecesUom->id,
            'conversion_factor' => 12,
        ]);

        // 2. Setup Product with Base UOM = Pieces
        $product = Product::create([
            'product_code' => 'P-UOM',
            'name' => 'UOM Product',
            'uom_id' => $piecesUom->id,
            'costing_method_id' => CostingMethod::where('name', 'fifo')->first()->id,
            'is_active' => true,
        ]);

        $statusId = TransactionStatus::where('name', 'posted')->first()->id;
        $receiptType = TransactionType::where('code', 'RCPT')->first()->id;

        // 3. Receive 1 Box @ $120/Box
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'R-UOM-1',
                'transaction_type_id' => $receiptType,
                'transaction_status_id' => $statusId,
                'transaction_date' => now(),
                'vendor_id' => $this->vendor->id,
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'uom_id' => $boxUom->id, // SPECIFY Box UOM
                    'quantity' => 1,
                    'unit_cost' => 120.00,
                ],
            ],
        ]);

        // 4. Verify QOH is 12 Pieces
        $inventory = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(12, (float) $inventory->quantity_on_hand);

        // 5. Verify Unit Cost converted to $10/Piece
        $this->assertEquals(10.00, (float) $inventory->average_cost);
    }
}
