<?php

namespace Tests\Feature;

use App\Helpers\UomHelper;
use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Location;
use App\Models\Product;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Inventory\StockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UomHelper::clearCache();
        $this->seed(DatabaseSeeder::class);
        $this->seed(VendorSeeder::class);
    }

    public function test_missing_uom_conversion_returns_422_on_api(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);

        $pcs = UnitOfMeasure::where('abbreviation', 'pcs')->firstOrFail();
        $box = UnitOfMeasure::where('abbreviation', 'bx')->firstOrFail();

        $product = Product::create([
            'product_code' => 'UOM-STRICT-1',
            'name' => 'UOM Strict Test',
            'category_id' => Category::first()->id,
            'uom_id' => $pcs->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 1,
            'is_active' => true,
        ]);

        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();
        $vendor = Vendor::firstOrFail();
        $posted = TransactionStatus::where('name', 'posted')->firstOrFail();
        $receiptType = TransactionType::where('code', 'RCPT')->firstOrFail();

        $this->postJson('/api/transactions', [
            'header' => [
                'transaction_type_id' => $receiptType->id,
                'transaction_status_id' => $posted->id,
                'transaction_date' => now()->toDateString(),
                'vendor_id' => $vendor->id,
                'to_location_id' => $location->id,
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => 1,
                    'unit_cost' => 10,
                    'uom_id' => $box->id,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_reversal_links_original_transaction(): void
    {
        $vendor = Vendor::firstOrFail();
        $location = Location::where('code', 'WH-A-Z1')->firstOrFail();
        $product = Product::create([
            'product_code' => 'REV-LINK-1',
            'name' => 'Reversal Link Test',
            'category_id' => Category::first()->id,
            'uom_id' => UnitOfMeasure::first()->id,
            'costing_method_id' => CostingMethod::first()->id,
            'selling_price' => 1,
            'is_active' => true,
        ]);

        $posted = TransactionStatus::where('name', 'posted')->firstOrFail();
        $receiptType = TransactionType::where('code', 'RCPT')->firstOrFail();

        $service = app(StockService::class);
        $original = $service->recordMovement([
            'header' => [
                'reference_number' => 'R-LINK-ORIG',
                'transaction_type_id' => $receiptType->id,
                'transaction_status_id' => $posted->id,
                'transaction_date' => now()->toDateString(),
                'vendor_id' => $vendor->id,
                'to_location_id' => $location->id,
            ],
            'lines' => [
                [
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'quantity' => 5,
                    'unit_cost' => 10,
                ],
            ],
        ]);

        $reversal = $service->reverseTransaction($original);

        $this->assertSame($original->id, (int) $reversal->reverses_transaction_id);
        $this->assertDatabaseHas('transactions', [
            'id' => $reversal->id,
            'reverses_transaction_id' => $original->id,
        ]);
    }
}
