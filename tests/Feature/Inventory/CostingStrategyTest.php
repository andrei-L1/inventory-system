<?php

namespace Tests\Feature\Inventory;

use App\Helpers\UomHelper;
use App\Models\CostingMethod;
use App\Models\Inventory;
use App\Models\InventoryCostLayer;
use App\Models\Location;
use App\Models\Product;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostingStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);
        UomHelper::clearCache();
        $this->seed(DatabaseSeeder::class);
    }

    /** @test */
    public function test_fifo_strategy_uses_oldest_layers()
    {
        $product = $this->createProduct('fifo');
        $location = Location::where('code', 'WH-A-Z1')->first();

        // 1. Receipt 10 @ 100
        $this->postReceipt($product, $location, 10, 100);
        // 2. Receipt 10 @ 200
        $this->postReceipt($product, $location, 10, 200);

        // 3. Issue 5 -> Should use 100
        $issue = $this->postIssue($product, $location, 5);
        $this->assertEquals(100.0, (float) $issue->lines->first()->unit_cost);

        // 4. Issue 10 -> Should use weighted (5 @ 100 + 5 @ 200) / 10 = 150
        $issue2 = $this->postIssue($product, $location, 10);
        $this->assertEquals(150.0, (float) $issue2->lines->first()->unit_cost);
    }

    /** @test */
    public function test_lifo_strategy_uses_newest_layers()
    {
        $product = $this->createProduct('lifo');
        $location = Location::where('code', 'WH-A-Z1')->first();

        // 1. Receipt 10 @ 100
        $this->postReceipt($product, $location, 10, 100);
        // 2. Receipt 10 @ 200
        $this->postReceipt($product, $location, 10, 200);

        // 3. Issue 5 -> Should use 200 (Newest Layer)
        $issue = $this->postIssue($product, $location, 5);
        $this->assertEquals(200.0, (float) $issue->lines->first()->unit_cost);
    }

    /** @test */
    public function test_average_strategy_levels_layers_on_receipt()
    {
        $product = $this->createProduct('average');
        $location = Location::where('code', 'WH-A-Z1')->first();

        // 1. Receipt 10 @ 100
        $this->postReceipt($product, $location, 10, 100);
        // 2. Receipt 10 @ 200 -> New Avg should be 150
        $this->postReceipt($product, $location, 10, 200);

        $inventory = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->first();
        $this->assertEquals(150.0, (float) $inventory->average_cost);

        // ASSERT: All layers should be leveled to 150
        $layers = InventoryCostLayer::where('product_id', $product->id)->get();
        foreach ($layers as $layer) {
            $this->assertEquals(150.0, (float) $layer->unit_cost, "Layer should be leveled to 150");
        }

        // 3. Issue 5 -> Should use 150
        $issue = $this->postIssue($product, $location, 5);
        $this->assertEquals(150.0, (float) $issue->lines->first()->unit_cost);
        
        // 4. Financial Invariant: Sum(Layers Value) == QOH * AvgCost
        $qoh = (float) $inventory->fresh()->quantity_on_hand;
        $avg = (float) $inventory->fresh()->average_cost;
        $totalLayerValue = InventoryCostLayer::where('product_id', $product->id)
            ->get()
            ->sum(fn($l) => ($l->received_qty - $l->issued_qty) * $l->unit_cost);
            
        $this->assertEquals($qoh * $avg, $totalLayerValue, "Financial invariant failed");
    }

    /** @test */
    public function test_partial_consumption_crosses_layers_correctly()
    {
        $product = $this->createProduct('fifo');
        $location = Location::where('code', 'WH-A-Z1')->first();

        $this->postReceipt($product, $location, 10, 100);
        $this->postReceipt($product, $location, 10, 200);

        // Issue 15 (10 @ 100 + 5 @ 200) = 1000 + 1000 = 2000 / 15 = 133.333333
        $issue = $this->postIssue($product, $location, 15);
        $this->assertEquals(round(2000/15, 8), round((float)$issue->lines->first()->unit_cost, 8));
    }

    // Helpers
    protected function createProduct($methodName)
    {
        return Product::create([
            'product_code' => "TEST-" . strtoupper($methodName),
            'name' => "Test " . $methodName,
            'uom_id' => UnitOfMeasure::where('abbreviation', 'pcs')->first()->id,
            'costing_method_id' => CostingMethod::where('name', $methodName)->first()->id,
            'is_active' => true,
        ]);
    }

    protected function postReceipt($product, $location, $qty, $cost)
    {
        $vendor = \App\Models\Vendor::firstOrCreate(
            ['vendor_code' => 'TEST-VENDOR'],
            ['name' => 'Test Vendor', 'is_active' => true]
        );
        
        $transactionType = TransactionType::where('code', 'RCPT')->first();
        $status = TransactionStatus::where('name', 'posted')->first();
        
        return $this->service->recordMovement([
            'header' => [
                'reference_number' => 'TEST-REC-' . uniqid(),
                'transaction_type_id' => $transactionType->id,
                'transaction_status_id' => $status->id,
                'transaction_date' => now()->toDateString(),
                'vendor_id' => $vendor->id,
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'uom_id' => $product->uom_id,
                ],
            ],
        ]);
    }

    protected function postIssue($product, $location, $qty)
    {
        return $this->service->recordMovement([
            'header' => [
                'reference_number' => uniqid('ISS-'),
                'transaction_type_id' => TransactionType::where('code', 'ISSU')->first()->id,
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->first()->id,
                'transaction_date' => now(),
            ],
            'lines' => [
                ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => -$qty, 'unit_cost' => 0],
            ],
        ]);
    }
}
