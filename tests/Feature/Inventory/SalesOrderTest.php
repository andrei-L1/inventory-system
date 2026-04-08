<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesOrderStatus;
use App\Models\Transaction;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Helpers\UomHelper;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        UomHelper::clearCache();
        $this->stockService = app(StockService::class);

        // Setup lookup data first
        $this->seedLookupData();

        // Create user with admin role to bypass permission checks
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['is_active' => true]);
        $this->user = User::factory()->create(['role_id' => $adminRole->id]);
    }

    protected function seedLookupData()
    {
        // 1. Transaction Types
        TransactionType::firstOrCreate(['code' => 'RECP'], ['name' => 'receipt', 'is_debit' => true]);
        TransactionType::firstOrCreate(['code' => 'ISSU'], ['name' => 'issue', 'is_debit' => false]);

        // 2. Transaction Statuses
        TransactionStatus::firstOrCreate(['name' => 'draft']);
        TransactionStatus::firstOrCreate(['name' => 'posted']);

        // 3. Sales Order Statuses
        $statuses = [
            ['name' => SalesOrderStatus::QUOTATION, 'is_editable' => true],
            ['name' => SalesOrderStatus::CONFIRMED, 'is_editable' => false],
            ['name' => SalesOrderStatus::PICKED, 'is_editable' => false],
            ['name' => SalesOrderStatus::PACKED, 'is_editable' => false],
            ['name' => SalesOrderStatus::SHIPPED, 'is_editable' => false],
            ['name' => SalesOrderStatus::PARTIALLY_SHIPPED, 'is_editable' => false],
            ['name' => SalesOrderStatus::CANCELLED, 'is_editable' => false],
        ];
        foreach ($statuses as $status) {
            SalesOrderStatus::firstOrCreate(['name' => $status['name']], $status);
        }

        // 4. Location Types
        LocationType::firstOrCreate(['name' => 'Warehouse']);

        // 5. Costing Methods
        CostingMethod::updateOrCreate(
            ['name' => 'fifo'],
            ['label' => 'FIFO', 'is_active' => true]
        );

        // 6. Mandatory Categories & UOMs for factories to reference
        Category::firstOrCreate(['name' => 'General']);
        UnitOfMeasure::firstOrCreate(
            ['abbreviation' => 'pcs'],
            ['name' => 'Piece', 'category' => 'count', 'is_base' => true, 'decimals' => 0, 'is_active' => true]
        );
    }

    public function test_can_create_sales_order()
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $location = Location::factory()->create();
        $uom = UnitOfMeasure::factory()->create();

        $payload = [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'uom_id' => $uom->id,
                    'ordered_qty' => 10,
                    'unit_price' => 100,
                    'tax_rate' => 12,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/sales-orders', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sales_orders', ['customer_id' => $customer->id]);
        $this->assertDatabaseHas('sales_order_lines', [
            'product_id' => $product->id,
            'ordered_qty' => 10,
            'unit_price' => 100,
        ]);

        $so = SalesOrder::first();
        // Check subtotal calculation: 10 * 100 * 1.12 = 1120
        $this->assertEquals(1120, (float) $so->total_amount);
    }

    public function test_can_confirm_sales_order_and_reserve_stock()
    {
        $product = Product::factory()->create(['uom_id' => 1]); // Assuming 1 is base UOM for simplicity in test
        $location = Location::factory()->create();
        $uom = UnitOfMeasure::firstOrCreate(
            ['id' => 1],
            ['name' => 'Piece', 'abbreviation' => 'pcs', 'category' => 'count', 'is_base' => true, 'decimals' => 0, 'is_active' => true]
        );
        UomHelper::clearCache();
        $product->update(['uom_id' => $uom->id]);

        // 1. Add some stock first (Receipt)
        $this->stockService->recordMovement([
            'header' => [
                'transaction_type_id' => TransactionType::where('code', 'RECP')->value('id'),
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->value('id'),
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'RECP-001',
            ],
            'lines' => [[
                'product_id' => $product->id,
                'location_id' => $location->id,
                'quantity' => 50,
                'unit_cost' => 45,
            ]],
        ]);

        $so = SalesOrder::factory()->create([
            'status_id' => SalesOrderStatus::where('name', SalesOrderStatus::QUOTATION)->value('id'),
        ]);
        $line = SalesOrderLine::factory()->create([
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uom_id' => $uom->id,
            'ordered_qty' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/sales-orders/{$so->id}/approve");

        $response->assertStatus(200);

        $inventory = Inventory::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();

        $this->assertEquals(10, (float) $inventory->reserved_qty);
        $this->assertEquals(50, (float) $inventory->quantity_on_hand);

        $so->refresh();
        $this->assertEquals(SalesOrderStatus::CONFIRMED, $so->status->name);
    }

    public function test_can_ship_sales_order_and_reconcile_inventory_and_reservation()
    {
        $product = Product::factory()->create();
        $location = Location::factory()->create();
        $uom = $product->uom;

        // 1. Add some stock (50 units)
        $this->stockService->recordMovement([
            'header' => [
                'transaction_type_id' => TransactionType::where('code', 'RECP')->value('id'),
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->value('id'),
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'RECP-001',
            ],
            'lines' => [[
                'product_id' => $product->id,
                'location_id' => $location->id,
                'quantity' => 50,
                'unit_cost' => 40,
            ]],
        ]);

        // 2. Create and Confirm (Reserve 10)
        $so = SalesOrder::factory()->create(['status_id' => SalesOrderStatus::where('name', SalesOrderStatus::QUOTATION)->value('id')]);
        $line = SalesOrderLine::factory()->create([
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uom_id' => $uom->id,
            'ordered_qty' => 10,
        ]);

        $this->actingAs($this->user)->patchJson("/api/sales-orders/{$so->id}/approve");

        // 3. Fulfill (Pick -> Pack -> Ship)
        // Pick
        $this->actingAs($this->user)->patchJson("/api/sales-orders/{$so->id}/pick", [
            'lines' => [['so_line_id' => $line->id, 'picked_qty' => 10]],
        ])->assertStatus(200);

        // Pack
        $this->actingAs($this->user)->patchJson("/api/sales-orders/{$so->id}/pack", [
            'lines' => [['so_line_id' => $line->id, 'packed_qty' => 10]],
        ])->assertStatus(200);

        // Ship
        $payload = [
            'lines' => [
                [
                    'so_line_id' => $line->id,
                    'shipped_qty' => 10,
                ],
            ],
            'carrier' => 'FedEx',
            'tracking_number' => 'TRK123456',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/sales-orders/{$so->id}/ship", $payload);

        $response->assertStatus(200);

        $inventory = Inventory::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();

        // 4. Verify Stock: 50 - 10 = 40
        $this->assertEquals(40, (float) $inventory->quantity_on_hand);
        // 5. Verify Reservation: 10 - 10 = 0
        $this->assertEquals(0, (float) $inventory->reserved_qty);

        // 6. Verify Transaction created
        $this->assertDatabaseHas('transactions', [
            'sales_order_id' => $so->id,
            'transaction_type_id' => TransactionType::where('name', 'issue')->value('id'),
        ]);

        $transaction = Transaction::where('sales_order_id', $so->id)->first();
        $this->assertEquals(40, (float) $transaction->lines->first()->unit_cost); // COGS should be 40
    }
}
