<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'phone' => '+9989'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'shop_name' => fake()->company(),
            'business_type' => 'boshqa',
            'locale' => 'uz',
            'phone_verified_at' => now(),
            'pin' => '1234',
        ];
    }
}
