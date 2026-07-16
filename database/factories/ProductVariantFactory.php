<?php
// database/factories/ProductVariantFactory.php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'price_override' => null,
            'options' => [
                'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
                'color' => fake()->safeColorName(),
            ],
        ];
    }

    public function withPriceOverride(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'price_override' => $price,
        ]);
    }
}