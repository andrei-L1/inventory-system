<?php

namespace Tests\Feature\Inventory;

use App\Models\CostingMethod;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Location;
use App\Models\Product;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Services\Inventory\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);

        // Use the system's database seeder to set up lookups and locations
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fifo_consumption_consumes_oldest_layers_first()
    {
        $location = Location::where('code', 'WH-A-Z1')->first();
        $fifoMethod = CostingMethod::where('name', 'fifo')->first();

        // Manual product creation as no factory exists
        $product = Product::create([
            'product_code' => 'TEST-001',
            'name' => 'Test Product FIFO',
            'costing_method_id' => $fifoMethod->id,
            'is_active' => true,
        ]);

        // 1. Receive 10 @ $10 (Layer 1 - Older)
        InventoryCostLayer::create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'received_qty' => 10,
            'unit_cost' => 10.00,
            'receipt_date' => now()->subDays(2),
            'is_exhausted' => false,
        ]);

        // 2. Receive 10 @ $20 (Layer 2 - Newer)
        InventoryCostLayer::create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'received_qty' => 10,
            'unit_cost' => 20.00,
            'receipt_date' => now()->subDay(),
            'is_exhausted' => false,
        ]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 20,
        ]);

        // 3. Issue 15
        // Should consume all of Layer 1 (10) and 5 from Layer 2.
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'TRX-001',
                'transaction_type_id' => TransactionType::where('code', 'ISSU')->first()->id,
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->first()->id,
                'transaction_date' => now(),
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => -15, // Issue
                    'unit_cost' => 0,
                    // Note: StockService expects negative quantity for issues
                    // and handles it in recordMovement loop.
                ],
            ],
        ]);

        // Refresh layers from DB to get updated issued_qty and calculated remaining_qty
        $layer1 = InventoryCostLayer::where('product_id', $product->id)->where('unit_cost', 10.00)->first();
        $layer2 = InventoryCostLayer::where('product_id', $product->id)->where('unit_cost', 20.00)->first();

        $this->assertTrue($layer1->is_exhausted, 'Layer 1 should be exhausted');
        $this->assertEquals(10, (float) $layer1->issued_qty);

        $this->assertFalse($layer2->is_exhausted, 'Layer 2 should NOT be exhausted');
        $this->assertEquals(5, (float) $layer2->issued_qty);
    }

    public function test_lifo_consumption_consumes_newest_layers_first()
    {
        $location = Location::where('code', 'WH-A-Z1')->first();
        $lifoMethod = CostingMethod::where('name', 'lifo')->first();

        $product = Product::create([
            'product_code' => 'TEST-002',
            'name' => 'Test Product LIFO',
            'costing_method_id' => $lifoMethod->id,
            'is_active' => true,
        ]);

        // 1. Receive 10 @ $10 (Layer 1 - Older)
        InventoryCostLayer::create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'received_qty' => 10,
            'unit_cost' => 10.00,
            'receipt_date' => now()->subDays(2),
            'is_exhausted' => false,
        ]);

        // 2. Receive 10 @ $20 (Layer 2 - Newer)
        InventoryCostLayer::create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'received_qty' => 10,
            'unit_cost' => 20.00,
            'receipt_date' => now()->subDay(),
            'is_exhausted' => false,
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 20,
        ]);

        // 3. Issue 15
        // Should consume all of Layer 2 (10) and 5 from Layer 1.
        $this->service->recordMovement([
            'header' => [
                'reference_number' => 'TRX-002',
                'transaction_type_id' => TransactionType::where('code', 'ISSU')->first()->id,
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->first()->id,
                'transaction_date' => now(),
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => -15,
                    'unit_cost' => 0,
                ],
            ],
        ]);

        $layer1 = InventoryCostLayer::where('product_id', $product->id)->where('unit_cost', 10.00)->first();
        $layer2 = InventoryCostLayer::where('product_id', $product->id)->where('unit_cost', 20.00)->first();

        $this->assertTrue($layer2->is_exhausted, 'Layer 2 (Newer) should be exhausted');
        $this->assertEquals(10, (float) $layer2->issued_qty);

        $this->assertFalse($layer1->is_exhausted, 'Layer 1 (Older) should NOT be exhausted');
        $this->assertEquals(5, (float) $layer1->issued_qty);
    }
}
