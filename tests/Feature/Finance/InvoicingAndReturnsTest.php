<?php

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
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

class InvoicingAndReturnsTest extends TestCase
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

    public function test_sales_return_increases_stock_and_decrements_shipped_qty(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        // 1. Setup Data
        $customer = Customer::create(['name' => 'Test Customer', 'customer_code' => 'CUST001']);
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();
        $product = Product::create([
            'product_code' => 'RET-PROD-1',
            'name' => 'Returnable Product',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id,
            'costing_method_id' => CostingMethod::where('name', 'fifo')->first()->id,
            'selling_price' => 100,
            'is_active' => true,
        ]);

        // 2. Create and Ship SO
        $so = SalesOrder::create([
            'so_number' => 'SO-TEST-001',
            'customer_id' => $customer->id,
            'status_id' => SalesOrderStatus::where('name', SalesOrderStatus::SHIPPED)->first()->id,
            'order_date' => now(),
            'total_amount' => 1000,
        ]);

        $soLine = $so->lines()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uom_id' => $product->uom_id,
            'ordered_qty' => 10,
            'shipped_qty' => 10,
            'unit_price' => 100,
            'subtotal' => 1000,
        ]);

        // Mock some stock levels so SRET doesn't fail (though StockService handles receipts fine)

        // 3. Perform Return
        $response = $this->postJson("/api/sales-orders/{$so->id}/return", [
            'location_id' => $location->id,
            'lines' => [
                [
                    'so_line_id' => $soLine->id,
                    'returned_qty' => 4,
                    'resolution' => 'refund',
                    'reason' => 'Defective',
                ],
            ],
            'notes' => 'Customer return request',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Sales Return processed successfully.');

        // 4. Verify Quantities (Strategy B: Physical Truth Counter)
        $soLine->refresh();
        $this->assertEquals(6, (float) $soLine->shipped_qty, 'Shipped quantity should decrease upon return');
        $this->assertEquals(6, (float) $soLine->net_shipped_qty, 'Net Shipped accessor should align with counter');
        $this->assertEquals(6, (float) $soLine->requirement_qty, 'Requirement should be 6 after refund');
        $this->assertEquals(4, (float) $soLine->returned_qty, 'Returned quantity should match exactly');

        // 5. Verify Stock increased (Inventory QOH)
        $inventory = Inventory::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $this->assertEquals(4, (float) $inventory->quantity_on_hand);

        // 6. Verify Credit Note was created
        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $so->id,
            'type' => 'CREDIT_NOTE',
            'total_amount' => 400,
        ]);
    }

    public function test_cannot_approve_so_over_customer_credit_limit(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        // 1. Setup Customer with Limit
        $customer = Customer::create([
            'name' => 'Limited Customer',
            'customer_code' => 'CUST_LIM',
            'credit_limit' => 500,
        ]);

        // 2. Setup Order worth 1000
        $so = SalesOrder::create([
            'so_number' => 'SO-LIMIT-1',
            'customer_id' => $customer->id,
            'status_id' => SalesOrderStatus::where('name', SalesOrderStatus::QUOTATION)->first()->id,
            'order_date' => now(),
            'total_amount' => 1000,
        ]);

        // 3. Attempt to Approve
        $response = $this->patchJson("/api/sales-orders/{$so->id}/approve");

        $response->assertStatus(422);
        // Flexible check for message content
        $response->assertJsonPath('message', function ($message) {
            return str_contains($message, 'Credit Limit Exceeded') &&
                   str_contains($message, '500') &&
                   str_contains($message, '1000');
        });
    }

    public function test_invoice_generation_from_sales_order(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $customer = Customer::create(['name' => 'Invoice Customer', 'customer_code' => 'CUST_INV']);
        $product = Product::create([
            'product_code' => 'INV-P1',
            'name' => 'Invoiceable Product',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 100,
            'is_active' => true,
        ]);

        $so = SalesOrder::create([
            'so_number' => 'SO-INV-1',
            'customer_id' => $customer->id,
            'status_id' => SalesOrderStatus::where('name', SalesOrderStatus::SHIPPED)->first()->id,
            'order_date' => now(),
            'total_amount' => 500,
        ]);

        $soLine = $so->lines()->create([
            'product_id' => $product->id,
            'location_id' => Location::first()->id,
            'uom_id' => $product->uom_id,
            'ordered_qty' => 5,
            'shipped_qty' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
        ]);

        // Create Invoice
        $response = $this->postJson("/api/sales-orders/{$so->id}/invoice", [
            'lines' => [
                [
                    'so_line_id' => $soLine->id,
                    'quantity' => 5,
                ],
            ],
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $so->id,
            'total_amount' => 500,
            'status' => 'DRAFT',
        ]);
    }

    public function test_payment_allocation_to_invoice(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $customer = Customer::create(['name' => 'Payer', 'customer_code' => 'PAYER1']);

        // 1. Create Posted Invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PAY-1',
            'customer_id' => $customer->id,
            'invoice_date' => now(),
            'total_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'OPEN',
        ]);

        // 2. Record Payment
        $paymentRes = $this->postJson('/api/payments', [
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'amount' => 1200,
            'payment_method' => 'Cash',
        ]);
        $paymentRes->assertStatus(201);
        $paymentId = $paymentRes->json('payment.id');

        // 3. Allocate Payment
        $allocRes = $this->postJson("/api/payments/{$paymentId}/allocate", [
            'allocations' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 1000,
                ],
            ],
        ]);

        $allocRes->assertStatus(200);

        // 4. Verify Invoice Status
        $invoice->refresh();
        $this->assertEquals(1000, (float) $invoice->paid_amount);
        $this->assertEquals('PAID', $invoice->status);

        // 5. Verify Payment balance
        $payment = Payment::findOrFail($paymentId);
        $this->assertEquals(200, (float) $payment->unallocated_amount);
    }
}
