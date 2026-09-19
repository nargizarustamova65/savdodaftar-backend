<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['shop_name' => 'Bozor Market']);
        Sanctum::actingAs($this->user);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->for($this->user)->create($overrides + [
            'name' => 'Futbolka',
            'buy_price' => 70000,
            'sell_price' => 120000,
            'stock' => 50,
            'min_stock' => 5,
        ]);
    }

    private function customer(): Customer
    {
        return Customer::factory()->for($this->user)->create(['name' => 'Ali aka', 'balance' => 0]);
    }

    public function test_cash_sale_reduces_stock_and_calculates_profit(): void
    {
        $shirt = $this->product();
        $pants = $this->product(['name' => 'Shim', 'buy_price' => 100000, 'sell_price' => 180000, 'stock' => 8]);

        $response = $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $shirt->id, 'qty' => 2],
                ['product_id' => $pants->id, 'qty' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.subtotal', 420000)
            ->assertJsonPath('data.total', 420000)
            ->assertJsonPath('data.paid_cash', 420000)
            ->assertJsonPath('data.debt_amount', 0)
            ->assertJsonPath('data.total_cost', 240000)
            ->assertJsonPath('data.profit', 180000)
            ->assertJsonPath('data.net_profit', 180000)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Futbolka')
            ->assertJsonPath('data.items.0.price', 120000)
            ->assertJsonPath('data.items.0.profit', 100000)
            ->assertJsonPath('message', "Savdo yakunlandi. Jami: 420 000 so'm.");

        $saleId = $response->json('data.id');

        $this->assertSame(48.0, (float) $shirt->fresh()->stock);
        $this->assertSame(7.0, (float) $pants->fresh()->stock);

        $this->getJson("/api/v1/products/{$shirt->id}/movements?type=sale")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.signed_qty', -2)
            ->assertJsonPath('data.0.reference_type', 'sale')
            ->assertJsonPath('data.0.reference_id', $saleId);

        $this->getJson("/api/v1/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonCount(2, 'data.items');

        $this->getJson("/api/v1/sales/{$saleId}/audit")->assertOk()->assertJsonPath('data.0.action', 'created');
    }

    public function test_debt_sale_creates_debt_and_increases_customer_balance(): void
    {
        $shirt = $this->product();
        $customer = $this->customer();

        $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'debt',
            'due_date' => '2026-09-30',
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.debt_amount', 240000)
            ->assertJsonPath('data.debt.amount', 240000)
            ->assertJsonPath('data.debt.status', 'open')
            ->assertJsonPath('data.debt.due_date', '2026-09-30')
            ->assertJsonPath('data.customer.balance', 240000);

        $this->assertSame(240000.0, (float) $customer->fresh()->balance);

        $this->getJson('/api/v1/debts?status=unpaid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sale_id', fn ($id) => is_int($id));

        // Qarzga savdo mijozsiz bo'lmaydi
        $this->postJson('/api/v1/sales', [
            'payment_method' => 'debt',
            'items' => [['product_id' => $shirt->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonPath('code', 'customer_required');

        $this->assertSame(48.0, (float) $shirt->fresh()->stock);
    }

    public function test_mixed_payment_fills_debt_remainder_and_rejects_mismatch(): void
    {
        $shirt = $this->product();
        $customer = $this->customer();

        $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'mixed',
            'paid_cash' => 100000,
            'paid_card' => 50000,
            'debt_amount' => 10000,
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_mismatch')
            ->assertJsonPath('meta.total', 240000)
            ->assertJsonPath('meta.paid', 160000);

        $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'mixed',
            'paid_cash' => 100000,
            'paid_card' => 40000,
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.paid_cash', 100000)
            ->assertJsonPath('data.paid_card', 40000)
            ->assertJsonPath('data.debt_amount', 100000)
            ->assertJsonPath('data.debt.amount', 100000);

        $this->assertSame(100000.0, (float) $customer->fresh()->balance);
    }

    public function test_discount_and_ad_hoc_item_without_product(): void
    {
        $shirt = $this->product();

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'card',
            'discount' => 20000,
            'items' => [
                ['product_id' => $shirt->id, 'qty' => 1],
                ['name' => 'Paket', 'qty' => 3, 'price' => 1000],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 123000)
            ->assertJsonPath('data.discount', 20000)
            ->assertJsonPath('data.total', 103000)
            ->assertJsonPath('data.paid_card', 103000)
            ->assertJsonPath('data.total_cost', 70000)
            ->assertJsonPath('data.profit', 33000)
            ->assertJsonPath('data.items.1.product_id', null)
            ->assertJsonPath('data.items.1.name', 'Paket')
            ->assertJsonPath('data.items.1.total', 3000);

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'discount' => 500000,
            'items' => [['product_id' => $shirt->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonPath('code', 'discount_exceeds_total');

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [['qty' => 1]],
        ])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_insufficient_stock_rolls_back_whole_sale(): void
    {
        $shirt = $this->product(['stock' => 10]);
        $pants = $this->product(['name' => 'Shim', 'stock' => 1]);

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $shirt->id, 'qty' => 2],
                ['product_id' => $pants->id, 'qty' => 3],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('meta.product_id', $pants->id);

        $this->assertSame(10.0, (float) $shirt->fresh()->stock);
        $this->assertSame(1.0, (float) $pants->fresh()->stock);
        $this->getJson('/api/v1/sales')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_partial_and_full_return_restore_stock_and_recalculate(): void
    {
        $shirt = $this->product();
        $pants = $this->product(['name' => 'Shim', 'buy_price' => 100000, 'sell_price' => 180000, 'stock' => 8]);

        $sale = $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $shirt->id, 'qty' => 2],
                ['product_id' => $pants->id, 'qty' => 1],
            ],
        ])->assertCreated()->json('data');

        $shirtItem = $sale['items'][0]['id'];

        $this->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['sale_item_id' => $shirtItem, 'qty' => 1]],
            'reason' => 'Mahsulot qaytarildi',
        ])
            ->assertCreated()
            ->assertJsonPath('data.return.total', 120000)
            ->assertJsonPath('data.return.refund_method', 'cash')
            ->assertJsonPath('data.return.reason', 'Mahsulot qaytarildi')
            ->assertJsonCount(1, 'data.return.items')
            ->assertJsonPath('data.sale.status', 'partially_returned')
            ->assertJsonPath('data.sale.returned_total', 120000)
            ->assertJsonPath('data.sale.net_total', 300000)
            ->assertJsonPath('data.sale.net_profit', 130000)
            ->assertJsonPath('data.sale.items.0.returned_qty', 1)
            ->assertJsonPath('data.sale.items.0.returnable_qty', 1)
            ->assertJsonPath('message', "Qaytarish saqlandi. Summa: 120 000 so'm.");

        $this->assertSame(49.0, (float) $shirt->fresh()->stock);

        // Sotilgandan ko'p qaytarib bo'lmaydi
        $this->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['sale_item_id' => $shirtItem, 'qty' => 2]],
        ])->assertStatus(422)->assertJsonPath('code', 'return_exceeds_sold');

        // items bo'sh — qolgan hamma narsa qaytariladi
        $this->postJson("/api/v1/sales/{$sale['id']}/return", [])
            ->assertCreated()
            ->assertJsonPath('data.return.total', 300000)
            ->assertJsonPath('data.sale.status', 'returned')
            ->assertJsonPath('data.sale.returned_total', 420000)
            ->assertJsonPath('data.sale.net_total', 0)
            ->assertJsonPath('data.sale.net_profit', 0);

        $this->assertSame(50.0, (float) $shirt->fresh()->stock);
        $this->assertSame(8.0, (float) $pants->fresh()->stock);

        $this->postJson("/api/v1/sales/{$sale['id']}/return", [])->assertStatus(422)->assertJsonPath('code', 'sale_already_returned');

        $this->getJson("/api/v1/products/{$shirt->id}/movements?type=sale_return")->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/sales/returns')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/v1/sales/{$sale['id']}")->assertOk()->assertJsonCount(2, 'data.returns');

        $audit = $this->getJson("/api/v1/sales/{$sale['id']}/audit")->json('data');
        $this->assertSame(['created', 'returned', 'returned'], array_column($audit, 'action'));
    }

    public function test_return_of_debt_sale_reduces_linked_debt(): void
    {
        $shirt = $this->product();
        $customer = $this->customer();

        $sale = $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'debt',
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.return.refund_method', 'debt')
            ->assertJsonPath('data.sale.debt.amount', 120000)
            ->assertJsonPath('data.sale.debt.remaining', 120000)
            ->assertJsonPath('data.sale.debt.status', 'open');

        $this->assertSame(120000.0, (float) $customer->fresh()->balance);

        // Qarz to'langan bo'lsa qarzdan ayirib bo'lmaydi
        $this->postJson("/api/v1/debts/{$sale['debt']['id']}/payments", ['amount' => 120000])->assertCreated();

        $this->postJson("/api/v1/sales/{$sale['id']}/return", ['refund_method' => 'debt'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'refund_exceeds_debt');

        $this->postJson("/api/v1/sales/{$sale['id']}/return", [])
            ->assertCreated()
            ->assertJsonPath('data.return.refund_method', 'cash')
            ->assertJsonPath('data.sale.status', 'returned');
    }

    public function test_summary_filters_and_receipt(): void
    {
        $shirt = $this->product();
        $pants = $this->product(['name' => 'Shim', 'buy_price' => 100000, 'sell_price' => 180000, 'stock' => 8]);
        $customer = $this->customer();

        $first = $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'card',
            'items' => [['product_id' => $pants->id, 'qty' => 1]],
        ])->assertCreated();

        $this->getJson('/api/v1/sales/summary')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.returns_count', 0)
            ->assertJsonPath('data.total', 420000)
            ->assertJsonPath('data.net_total', 420000)
            ->assertJsonPath('data.profit', 180000)
            ->assertJsonPath('data.cash', 240000)
            ->assertJsonPath('data.card', 180000)
            ->assertJsonPath('data.debt', 0)
            ->assertJsonPath('data.average_check', 210000);

        $this->getJson('/api/v1/sales/summary?from=2020-01-01&to=2020-01-31')->assertOk()->assertJsonPath('data.sales_count', 0);

        $this->getJson('/api/v1/sales')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.items_count', 1);
        $this->getJson('/api/v1/sales?payment_method=card')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/sales?customer_id={$customer->id}")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $first);
        $this->getJson('/api/v1/sales?search=Shim')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/sales?search=Ali')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $first);

        $this->getJson("/api/v1/sales/{$first}/receipt")
            ->assertOk()
            ->assertJsonPath('data.shop_name', 'Bozor Market')
            ->assertJsonPath('data.text', fn ($text) => str_contains($text, "Jami: 240 000 so'm")
                && str_contains($text, 'Futbolka')
                && str_contains($text, "To'lov: Naqd")
                && str_contains($text, 'Mijoz: Ali aka'));

        $types = collect($this->getJson("/api/v1/customers/{$customer->id}/history")->assertOk()->json('data.items'))->pluck('type');
        $this->assertTrue($types->contains('sale'));
    }

    public function test_client_uuid_makes_sale_idempotent(): void
    {
        $shirt = $this->product();
        $uuid = (string) Str::uuid();

        $payload = [
            'payment_method' => 'cash',
            'client_uuid' => $uuid,
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ];

        $a = $this->postJson('/api/v1/sales', $payload)->assertCreated()->json('data.id');
        $b = $this->postJson('/api/v1/sales', $payload)->assertCreated()->json('data.id');

        $this->assertSame($a, $b);
        $this->assertSame(48.0, (float) $shirt->fresh()->stock);
        $this->getJson('/api/v1/sales')->assertJsonPath('meta.total', 1);
    }

    public function test_other_users_data_is_not_accessible(): void
    {
        $foreignProduct = Product::factory()->create(['stock' => 10]);
        $foreignCustomer = Customer::factory()->create();

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [['product_id' => $foreignProduct->id, 'qty' => 1]],
        ])->assertNotFound()->assertJsonPath('code', 'product_not_found');

        $this->postJson('/api/v1/sales', [
            'customer_id' => $foreignCustomer->id,
            'payment_method' => 'cash',
            'items' => [['name' => 'Paket', 'qty' => 1, 'price' => 1000]],
        ])->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/sales')->assertOk()->assertJsonPath('meta.total', 0);
    }
}
