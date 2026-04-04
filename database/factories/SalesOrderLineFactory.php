<?php

namespace Database\Factories;

use App\Models\SalesOrderLine;
use App\Models\SalesOrder;
use App\Models\Product;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'uom_id' => UnitOfMeasure::factory(),
            'ordered_qty' => $this->faker->numberBetween(1, 100),
            'unit_price' => $this->faker->randomFloat(2, 10, 500),
            'tax_rate' => 0,
            'discount_rate' => 0,
            'subtotal' => 0,
        ];
    }
}
