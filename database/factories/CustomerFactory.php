<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'customer_code' => strtoupper($this->faker->unique()->lexify('CUST-????')),
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'billing_address' => $this->faker->address,
            'shipping_address' => $this->faker->address,
            'tax_number' => strtoupper($this->faker->lexify('TAX-????')),
            'credit_limit' => $this->faker->randomFloat(2, 500, 5000),
            'is_active' => true,
        ];
    }
}
