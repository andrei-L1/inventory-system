<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Product;
use App\Models\TransactionStatus;
use App\Models\TransactionType;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;

    protected Product $product;

    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);
        \App\Helpers\UomHelper::clearCache();

        // 1. Setup Master Data using DB directly for speed & reliability
        DB::table('units_of_measure')->updateOrInsert(
            ['name' => 'Piece'],
            ['abbreviation' => 'pcs', 'created_at' => now(), 'updated_at' => now()]
        );
        $uomId = DB::table('units_of_measure')->where('name', 'Piece')->value('id');

        DB::table('categories')->updateOrInsert(
            ['name' => 'Test Cat'],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $catId = DB::table('categories')->where('name', 'Test Cat')->value('id');

        DB::table('costing_methods')->updateOrInsert(
            ['name' => 'fifo'],
            ['label' => 'FIFO', 'created_at' => now(), 'updated_at' => now()]
        );
        $methodId = DB::table('costing_methods')->where('name', 'fifo')->value('id');

        $this->product = Product::create([
            'product_code' => 'TEST-PROD-001',
            'name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'uom_id' => $uomId,
            'category_id' => $catId,
            'costing_method_id' => $methodId,
            'selling_price' => 100,
        ]);

        $locType = LocationType::firstOrCreate(['name' => 'Warehouse']);

        $this->location = Location::create([
            'code' => 'WH-MAIN',
            'name' => 'Main WH',
            'location_type_id' => $locType->id,
        ]);

        // 2. Setup lookup tables for Transaction engine
        DB::table('transaction_statuses')->updateOrInsert(['name' => 'draft'], ['created_at' => now(), 'updated_at' => now()]);
        DB::table('transaction_statuses')->updateOrInsert(['name' => 'posted'], ['created_at' => now(), 'updated_at' => now()]);

        DB::table('transaction_types')->updateOrInsert(
            ['code' => 'RECP'],
            ['name' => 'receipt', 'is_debit' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('transaction_types')->updateOrInsert(
            ['code' => 'ISSU'],
            ['name' => 'issue', 'is_debit' => false, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function test_can_reserve_stock(): void
    {
        // 1. Initial Receipt
        $this->service->recordMovement([
            'header' => [
                'transaction_type_id' => TransactionType::where('code', 'RECP')->value('id'),
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->value('id'),
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'RECP-001',
            ],
            'lines' => [[
                'product_id' => $this->product->id,
                'location_id' => $this->location->id,
                'quantity' => 10,
                'unit_cost' => 50,
            ]],
        ]);

        // 2. Reserve 4
        $this->service->reserveStock($this->product, $this->location, 4);

        $inventory = Inventory::where('product_id', $this->product->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertEquals(10, $inventory->quantity_on_hand);
        $this->assertEquals(4, $inventory->reserved_qty);
    }

    public function test_cannot_reserve_more_than_available(): void
    {
        // 1. Initial Receipt
        $this->service->recordMovement([
            'header' => [
                'transaction_type_id' => TransactionType::where('code', 'RECP')->value('id'),
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->value('id'),
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'RECP-001',
            ],
            'lines' => [[
                'product_id' => $this->product->id,
                'location_id' => $this->location->id,
                'quantity' => 10,
                'unit_cost' => 50,
            ]],
        ]);

        $this->expectException(InsufficientStockException::class);
        $this->service->reserveStock($this->product, $this->location, 11);
    }

    public function test_cannot_issue_unplanned_stock_if_it_is_reserved(): void
    {
        // 1. Initial Receipt of 10
        $this->service->recordMovement([
            'header' => [
                'transaction_type_id' => TransactionType::where('code', 'RECP')->value('id'),
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->value('id'),
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'RECP-001',
            ],
            'lines' => [[
                'product_id' => $this->product->id,
                'location_id' => $this->location->id,
                'quantity' => 10,
                'unit_cost' => 50,
            ]],
        ]);

        // 2. Reserve 7 (Available unreserved = 3)
        $this->service->reserveStock($this->product, $this->location, 7);

        // 3. Try to issue 4 (unreserved is only 3)
        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Insufficient unreserved stock');

        $this->service->recordMovement([
            'header' => [
                'transaction_type_id' => TransactionType::where('code', 'ISSU')->value('id'),
                'transaction_status_id' => TransactionStatus::where('name', 'posted')->value('id'),
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'ISSU-001',
            ],
            'lines' => [[
                'product_id' => $this->product->id,
                'location_id' => $this->location->id,
                'quantity' => -4,
            ]],
        ]);
    }

    public function test_can_release_reservation(): void
    {
        $inventory = Inventory::create([
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'reserved_qty' => 5,
        ]);

        $this->service->releaseReservation($this->product, $this->location, 2);

        $inventory->refresh();
        $this->assertEquals(3, $inventory->reserved_qty);
    }
}
