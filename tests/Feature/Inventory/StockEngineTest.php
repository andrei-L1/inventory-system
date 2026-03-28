<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\CostingMethod;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\Vendor;
use App\Services\Inventory\StockService;
use Database\Seeders\DatabaseSeeder;
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
        $this->vendor = Vendor::where('code', 'VEND-001')->first();
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
            'lines' => [['product_id' => $product->id, 'location_id' => $locA->id, 'quantity' => 100, 'unit_cost' => 1.00]]
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
}
