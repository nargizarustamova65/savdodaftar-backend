<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'phone' => '+998901234567',
            'name' => 'Demo foydalanuvchi',
            'shop_name' => 'Demo do\'kon',
            'pin' => '1234',
        ]);
    }
}
