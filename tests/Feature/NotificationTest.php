<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_notifications_are_derived_from_debts_and_stock(): void
    {
        $customer = $this->postJson('/api/v1/customers', ['name' => 'Ali aka'])
            ->assertCreated()
            ->json('data.id');

        // Muddati o'tgan va muddati yaqin qarzlar.
        $this->postJson('/api/v1/debts', [
            'customer_id' => $customer,
            'amount' => 100000,
            'due_date' => today()->subDay()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/v1/debts', [
            'customer_id' => $customer,
            'amount' => 50000,
            'due_date' => today()->addDay()->toDateString(),
        ])->assertCreated();

        // Tugagan va kam qolgan mahsulotlar.
        $this->postJson('/api/v1/products', ['name' => 'Shim', 'sell_price' => 60000, 'stock' => 0, 'min_stock' => 3])
            ->assertCreated();
        $this->postJson('/api/v1/products', ['name' => 'Futbolka', 'sell_price' => 50000, 'stock' => 2, 'min_stock' => 5])
            ->assertCreated();

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.count', 4)
            ->assertJsonPath('data.items.0.type', 'debt_overdue')
            ->assertJsonPath('data.items.0.name', 'Ali aka')
            ->assertJsonPath('data.items.1.type', 'debt_due_soon')
            ->assertJsonPath('data.items.2.type', 'out_of_stock')
            ->assertJsonPath('data.items.2.name', 'Shim')
            ->assertJsonPath('data.items.3.type', 'low_stock')
            ->assertJsonPath('data.items.3.name', 'Futbolka');
    }

    public function test_notifications_are_scoped_to_user(): void
    {
        $customer = $this->postJson('/api/v1/customers', ['name' => 'Ali aka'])->json('data.id');
        $this->postJson('/api/v1/debts', [
            'customer_id' => $customer,
            'amount' => 100000,
            'due_date' => today()->subDay()->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.count', 0);
    }
}
