<?php

namespace Tests\Feature\Sales;

use App\Helpers\FinancialMath;
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

class PrecisionReturnTest extends TestCase
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
     * Test mathematical integrity for Sales Returns using 8-decimal precision.
     */
    public function test_high_precision_sales_return_calculation(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        // 1. Setup high-precision product and customer
        $customer = Customer::create([
            'name' => 'High Precision Corp',
            'customer_code' => 'PREC001',
            'credit_limit' => 5000000,
        ]);

        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();

        $precisionPrice = 123.45678901;
        $product = Product::create([
            'product_code' => 'PREC-PART-7',
            'name' => 'Precision Electronic Part',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id, // Assuming pieces/grams
            'costing_method_id' => CostingMethod::where('name', 'fifo')->first()->id,
            'selling_price' => $precisionPrice,
            'is_active' => true,
        ]);

        // 2. Create Order with high-precision quantity
        // Total = 1.23456789 * 123.45678901 = 152.41578132711889
        // Rounded to 8 decimals = 152.41578133
        $orderQty = 1.23456789;
        $expectedLineTotal = round($orderQty * $precisionPrice, 8);

        $so = SalesOrder::create([
            'so_number' => 'SO-PREC-99',
            'customer_id' => $customer->id,
            'status_id' => SalesOrderStatus::where('name', SalesOrderStatus::SHIPPED)->first()->id,
            'order_date' => now(),
            'total_amount' => $expectedLineTotal,
        ]);

        $soLine = $so->lines()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uom_id' => $product->uom_id,
            'ordered_qty' => $orderQty,
            'shipped_qty' => $orderQty,
            'packed_qty' => $orderQty,
            'picked_qty' => $orderQty,
            'unit_price' => $precisionPrice,
            'subtotal' => $expectedLineTotal,
        ]);

        // 3. Perform High Precision Partial Return
        $returnQty = 0.54321098;

        // Expected credit note amount
        // Note: For document headers, we use headerTotal (2dp). For lines, we use 8dp.
        $expectedCreditTotalLine = FinancialMath::soLineSubtotal((string) $returnQty, (string) $precisionPrice);
        $expectedCreditTotalHeader = FinancialMath::headerTotal([$expectedCreditTotalLine]);

        $response = $this->postJson("/api/sales-orders/{$so->id}/return", [
            'location_id' => $location->id,
            'lines' => [
                [
                    'so_line_id' => $soLine->id,
                    'returned_qty' => $returnQty,
                    'resolution' => 'refund',
                    'reason' => 'Defective',
                ],
            ],
            'notes' => 'Precision test return',
        ]);

        $response->assertStatus(200);

        // 4. Verify Quantities in SO Line
        $soLine->refresh();
        $expectedNewShipped = round($orderQty - $returnQty, 8);

        $this->assertEquals($expectedNewShipped, (float) $soLine->shipped_qty, 'Shipped quantity should be decremented precisely');
        $this->assertEquals($returnQty, (float) $soLine->returned_qty, 'Returned quantity should match exactly');

        // 5. Verify Inventory QOH
        $inventory = Inventory::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $this->assertEquals($returnQty, (float) $inventory->quantity_on_hand, 'Stock should have increased by the precise return amount');

        // 6. Verify Credit Note precision
        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $so->id,
            'type' => 'CREDIT_NOTE',
            'total_amount' => $expectedCreditTotalHeader,
        ]);

        // Detailed check on credit note line
        $this->assertDatabaseHas('invoice_lines', [
            'product_id' => $product->id,
            'quantity' => $returnQty,
            'unit_price' => $precisionPrice,
            'subtotal' => $expectedCreditTotalLine,
        ]);
    }
}
