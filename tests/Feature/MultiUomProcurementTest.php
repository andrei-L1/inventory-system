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
use App\Models\UomConversion;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiUomProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(VendorSeeder::class);
    }

    public function test_purchase_order_with_custom_uom_converts_stock_correctly(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $vendor = Vendor::firstOrFail();
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();

        // 1. Setup UOMs: Piece (Base) and Box (10 Pieces)
        $pcsUom = UnitOfMeasure::where('abbreviation', 'pcs')->firstOrFail();
        $boxUom = UnitOfMeasure::where('abbreviation', 'bx')->firstOrFail();

        UomConversion::create([
            'from_uom_id' => $boxUom->id,
            'to_uom_id' => $pcsUom->id,
            'conversion_factor' => 10,
        ]);

        // 2. Create Product with Base UOM 'pcs'
        $product = Product::create([
            'product_code' => 'TEST-UOM-CONV',
            'name' => 'UOM Conversion Test Product',
            'category_id' => Category::first()->id,
            'uom_id' => $pcsUom->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 100,
            'is_active' => true,
        ]);

        // 3. Create PO with 5 Boxes (should be 50 pieces)
        $poRes = $this->postJson('/api/purchase-orders', [
            'vendor_id' => $vendor->id,
            'lines' => [
                [
                    'product_id' => $product->id, 
                    'uom_id' => $boxUom->id,
                    'ordered_qty' => 5, 
                    'unit_cost' => 500 // 500 per box
                ],
            ],
        ]);

        $poRes->assertStatus(201);
        $poId = $poRes->json('data.id');

        // Verify UOM is saved on PO line
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $poId,
            'product_id' => $product->id,
            'uom_id' => $boxUom->id,
            'ordered_qty' => 5
        ]);

        // 4. Approve PO
        $this->patchJson("/api/purchase-orders/{$poId}/approve")->assertOk();

        // 5. Receive 2 Boxes (should be 20 pieces)
        $poLineId = PurchaseOrderLine::where('purchase_order_id', $poId)->firstOrFail()->id;

        $this->postJson("/api/purchase-orders/{$poId}/receive", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'received_qty' => 2],
            ],
        ])->assertOk();

        // 6. Verify Inventory (StockService should have converted 2 boxes to 20 pieces)
        $inventory = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();
        $this->assertEquals(20.0, (float) $inventory->quantity_on_hand);

        // 7. Return 1 Box (should be 10 pieces)
        $this->postJson("/api/purchase-orders/{$poId}/return", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'return_qty' => 1, 'resolution' => 'replacement', 'reason' => 'Damaged box'],
            ],
        ])->assertOk();

        // 8. Verify Inventory (20 - 10 = 10 pieces)
        $inventory->refresh();
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);

        // 9. Verify PO Line Received Qty (should reflect original UOM unit: 2 received - 1 returned = 1 box)
        $poLine = PurchaseOrderLine::find($poLineId);
        $this->assertEquals(1.0, (float) $poLine->received_qty);
    }

    public function test_purchase_order_with_inverse_uom_conversion(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $vendor = Vendor::firstOrFail();
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();

        // 1. Setup UOMs: Box (Base) and Piece (1/10th of a Box)
        // Note: Defining Box -> Piece (10) but ordering in Pieces (Inverse)
        $pcsUom = UnitOfMeasure::where('abbreviation', 'pcs')->firstOrFail();
        $boxUom = UnitOfMeasure::where('abbreviation', 'bx')->firstOrFail();

        UomConversion::create([
            'from_uom_id' => $boxUom->id,
            'to_uom_id' => $pcsUom->id,
            'conversion_factor' => 10,
        ]);

        // 2. Create Product with Base UOM 'bx' (Box)
        $product = Product::create([
            'product_code' => 'TEST-INV-CONV',
            'name' => 'Inverse UOM Test Product',
            'category_id' => Category::first()->id,
            'uom_id' => $boxUom->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 1000, // 1000 per box
            'is_active' => true,
        ]);

        // 3. Create PO with 20 Pieces (should be 2 Boxes)
        $poRes = $this->postJson('/api/purchase-orders', [
            'vendor_id' => $vendor->id,
            'lines' => [
                [
                    'product_id' => $product->id, 
                    'uom_id' => $pcsUom->id,
                    'ordered_qty' => 20, 
                    'unit_cost' => 100 // 100 per piece
                ],
            ],
        ]);

        $poRes->assertStatus(201);
        $poId = $poRes->json('data.id');

        // 4. Approve and Receive
        $this->patchJson("/api/purchase-orders/{$poId}/approve")->assertOk();
        $poLineId = PurchaseOrderLine::where('purchase_order_id', $poId)->firstOrFail()->id;

        $this->postJson("/api/purchase-orders/{$poId}/receive", [
            'location_id' => $location->id,
            'lines' => [
                ['po_line_id' => $poLineId, 'received_qty' => 20],
            ],
        ])->assertOk();

        // 5. Verify Inventory (20 pieces / 10 factor = 2 boxes)
        $inventory = Inventory::where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();
        $this->assertEquals(2.0, (float) $inventory->quantity_on_hand);
    }
}
