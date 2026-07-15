<?php
// database/factories/PaymentFactory.php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => 'pending',
            'gateway_reference' => fake()->uuid(),
            'amount' => fake()->randomFloat(2, 10, 1000),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'succeeded']);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'failed']);
    }
}