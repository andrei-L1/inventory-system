<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'so_number' => 'SO-'.now()->format('Ymd-Hi').'-'.rand(1000, 9999),
            'customer_id' => Customer::factory(),
            'status_id' => SalesOrderStatus::where('name', 'quotation')->value('id') ?? 1,
            'order_date' => now(),
            'total_amount' => 0,
            'currency' => 'USD',
            'created_by' => User::factory(),
        ];
    }
}
