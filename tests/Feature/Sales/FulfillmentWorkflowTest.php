<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatus;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SalesOrderStatusSeeder;
use Database\Seeders\TransactionStatusSeeder;
use Database\Seeders\TransactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(TransactionTypeSeeder::class);
        $this->seed(TransactionStatusSeeder::class);
        $this->seed(SalesOrderStatusSeeder::class);
    }

    /**
     * Test that we can still pick items even if the SO is partially shipped.
     */
    public function test_can_pick_remaining_items_on_partially_shipped_order(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $customer = Customer::create(['name' => 'Fulfillment Corp', 'customer_code' => 'FULL01']);
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();
        $product = Product::create([
            'product_code' => 'WORK-1',
            'name' => 'Workflow Product',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 100,
            'is_active' => true,
        ]);

        // 1. Create a "Partially Shipped" order manually to simulate the state
        $so = SalesOrder::create([
            'so_number' => 'SO-FLOW-1',
            'customer_id' => $customer->id,
            'status_id' => SalesOrderStatus::where('name', SalesOrderStatus::PARTIALLY_SHIPPED)->first()->id,
            'order_date' => now(),
            'total_amount' => 1000,
        ]);

        $soLine = $so->lines()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uom_id' => $product->uom_id,
            'ordered_qty' => 10,
            'picked_qty' => 5,
            'packed_qty' => 5,
            'shipped_qty' => 5,
            'unit_price' => 100,
            'subtotal' => 1000,
        ]);

        // Verify initial state
        $this->assertEquals(SalesOrderStatus::PARTIALLY_SHIPPED, $so->status->name);

        // 2. Attempt to PICK the remaining 5 items
        $pickResponse = $this->patchJson("/api/sales-orders/{$so->id}/pick", [
            'lines' => [
                [
                    'so_line_id' => $soLine->id,
                    'picked_qty' => 5,
                ],
            ],
        ]);

        $pickResponse->assertStatus(200);
        $soLine->refresh();
        $so->refresh();

        // 3. Verify PICK success
        $this->assertEquals(10, (float) $soLine->picked_qty);
        // CRITICAL: Status must NOT have downgraded to 'picked' or 'partially_picked'
        $this->assertEquals(SalesOrderStatus::PARTIALLY_SHIPPED, $so->status->name, "Status should remain partially_shipped after picking more items.");

        // 4. Attempt to PACK the remaining 5
        $packResponse = $this->patchJson("/api/sales-orders/{$so->id}/pack", [
            'lines' => [
                [
                    'so_line_id' => $soLine->id,
                    'packed_qty' => 5,
                ],
            ],
        ]);

        $packResponse->assertStatus(200);
        $soLine->refresh();
        $so->refresh();

        // 5. Verify PACK success
        $this->assertEquals(10, (float) $soLine->packed_qty);
        $this->assertEquals(SalesOrderStatus::PARTIALLY_SHIPPED, $so->status->name, "Status should remain partially_shipped after packing more items.");
    }
}
