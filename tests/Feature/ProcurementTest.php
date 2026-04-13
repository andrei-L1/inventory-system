<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(VendorSeeder::class);
    }

    public function test_purchase_return_replacement_reduces_quantity_on_hand(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $vendor = Vendor::firstOrFail();
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();

        $product = Product::create([
            'product_code' => 'PO-RTV-REP',
            'name' => 'RTV Replacement',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 10,
            'is_active' => true,
        ]);

        $poRes = $this->postJson('/api/purchase-orders', [
            'vendor_id' => $vendor->id,
            'lines' => [
                ['product_id' => $product->id, 'ordered_qty' => 10, 'unit_cost' => 5, 'uom_id' => $product->uom_id],
            ],
        ]);

        $poRes->assertStatus(201);
        $poId = $poRes->json('data.id');

        $this->patchJson("/api/purchase-orders/{$poId}/approve")->assertOk();

        $poLineId = PurchaseOrderLine::where('purchase_order_id', $poId)->firstOrFail()->id;

        $this->postJson("/api/purchase-orders/{$poId}/receive", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'received_qty' => 10],
            ],
        ])->assertOk();

        $invBefore = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $invBefore->quantity_on_hand);

        $this->postJson("/api/purchase-orders/{$poId}/return", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'return_qty' => 3, 'resolution' => 'replacement', 'reason' => 'test'],
            ],
        ])->assertOk();

        $invAfter = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();
        $this->assertEqualsWithDelta(7.0, (float) $invAfter->quantity_on_hand, 0.0001);

        $line = PurchaseOrderLine::findOrFail($poLineId);
        $this->assertEqualsWithDelta(7.0, (float) $line->received_qty, 0.0001, 'Net Received should decrease upon return');
        $this->assertEqualsWithDelta(7.0, (float) $line->net_received_qty, 0.0001, 'Net Received should match the counter');
        $this->assertEqualsWithDelta(10.0, (float) $line->ordered_qty, 0.0001, 'Ordered Qty should remain immutable');
        $this->assertEqualsWithDelta(10.0, (float) $line->requirement_qty, 0.0001, 'Requirement stays at 10 for replacements');
    }

    public function test_purchase_return_credit_reduces_ordered_qty_and_totals(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $vendor = Vendor::firstOrFail();
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();

        $product = Product::create([
            'product_code' => 'PO-RTV-CR',
            'name' => 'RTV Credit',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 10,
            'is_active' => true,
        ]);

        $poRes = $this->postJson('/api/purchase-orders', [
            'vendor_id' => $vendor->id,
            'lines' => [
                ['product_id' => $product->id, 'ordered_qty' => 10, 'unit_cost' => 5, 'uom_id' => $product->uom_id],
            ],
        ]);

        $poRes->assertStatus(201);
        $this->assertEquals(50.0, (float) $poRes->json('data.total_amount'));

        $poId = $poRes->json('data.id');
        $this->patchJson("/api/purchase-orders/{$poId}/approve")->assertOk();

        $poLineId = PurchaseOrderLine::where('purchase_order_id', $poId)->firstOrFail()->id;

        $this->postJson("/api/purchase-orders/{$poId}/receive", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'received_qty' => 10],
            ],
        ])->assertOk();

        $this->postJson("/api/purchase-orders/{$poId}/return", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'return_qty' => 3, 'resolution' => 'credit', 'reason' => 'vendor credit'],
            ],
        ])->assertOk();

        $line = PurchaseOrderLine::findOrFail($poLineId);
        $this->assertEqualsWithDelta(10.0, (float) $line->ordered_qty, 0.0001, 'Gross Ordered should stay at 10');
        $this->assertEqualsWithDelta(7.0, (float) $line->requirement_qty, 0.0001, 'Net Requirement should be 7 after credit');
        $this->assertEqualsWithDelta(7.0, (float) $line->received_qty, 0.0001, 'Received Qty should decrease');
        $this->assertEqualsWithDelta(7.0, (float) $line->net_received_qty, 0.0001, 'Net Received should match');

        $poJson = $this->getJson("/api/purchase-orders/{$poId}")->assertOk()->json('data');
        // Total amount (contract) stays at 50, but Debit Note handles the $15 adjustment.
        $this->assertEqualsWithDelta(50.0, (float) $poJson['total_amount'], 0.01);
    }
}
