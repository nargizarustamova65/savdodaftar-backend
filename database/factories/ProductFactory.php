<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $buy = fake()->numberBetween(10, 500) * 1000;

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Futbolka', 'Shim', 'Ko\'ylak', 'Krossovka', 'Kurtka']).' '.fake()->numberBetween(1, 99),
            'category' => fake()->randomElement(['Kiyim', 'Poyabzal', 'Boshqa']),
            'barcode' => null,
            'unit' => Product::UNIT_DEFAULT,
            'buy_price' => $buy,
            'sell_price' => (int) round($buy * 1.5),
            'stock' => fake()->numberBetween(0, 100),
            'min_stock' => 5,
            'is_active' => true,
        ];
    }
}
