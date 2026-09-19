<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['shop_name' => 'Bozor Market']);
        Sanctum::actingAs($this->user);
    }

    /**
     * Bugungi ma'lumotlar:
     * - naqd savdo: 2 dona futbolka = 240 000 (foyda 100 000)
     * - qarzga savdo: 1 dona futbolka = 120 000 (foyda 50 000)
     * - xarajat: 30 000
     *
     * @return array{0: Product, 1: Customer}
     */
    private function seedData(): array
    {
        $shirt = Product::factory()->for($this->user)->create([
            'name' => 'Futbolka',
            'buy_price' => 70000,
            'sell_price' => 120000,
            'stock' => 50,
            'min_stock' => 5,
        ]);

        $customer = Customer::factory()->for($this->user)->create(['name' => 'Ali aka', 'balance' => 0]);

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [['product_id' => $shirt->id, 'qty' => 2]],
        ])->assertCreated();

        $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'debt',
            'items' => [['product_id' => $shirt->id, 'qty' => 1]],
        ])->assertCreated();

        $this->postJson('/api/v1/expenses', ['category' => 'transport', 'amount' => 30000])->assertCreated();

        return [$shirt, $customer];
    }

    public function test_dashboard_returns_cards_alerts_and_today_summary(): void
    {
        $this->seedData();

        // Qarzdan 50 000 qaytdi
        $debtId = $this->getJson('/api/v1/debts')->assertOk()->json('data.0.id');
        $this->postJson("/api/v1/debts/{$debtId}/payments", ['amount' => 50000])->assertCreated();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cards.today_sales', 360000)
            ->assertJsonPath('data.cards.today_profit', 150000)
            ->assertJsonPath('data.cards.debt_given', 120000)
            ->assertJsonPath('data.cards.debt_repaid', 50000)
            ->assertJsonPath('data.alerts.overdue_debts_count', 0)
            ->assertJsonPath('data.alerts.low_stock_count', 0)
            ->assertJsonPath('data.today.sales_count', 2)
            ->assertJsonPath('data.today.revenue', 360000)
            ->assertJsonPath('data.today.gross_profit', 150000)
            ->assertJsonPath('data.today.expenses', 30000)
            ->assertJsonPath('data.today.net_profit', 120000)
            ->assertJsonPath('data.today.paid_cash', 240000)
            ->assertJsonPath('data.today.paid_card', 0)
            ->assertJsonPath('data.today.on_debt', 120000);
    }

    public function test_overview_reports_net_profit_and_payment_breakdown(): void
    {
        $this->seedData();

        $this->getJson('/api/v1/reports/overview?period=day')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.revenue', 360000)
            ->assertJsonPath('data.gross_profit', 150000)
            ->assertJsonPath('data.expenses', 30000)
            ->assertJsonPath('data.net_profit', 120000)
            ->assertJsonPath('data.payments.cash', 240000)
            ->assertJsonPath('data.payments.card', 0)
            ->assertJsonPath('data.payments.debt', 120000)
            ->assertJsonPath('data.debts.given', 120000)
            ->assertJsonPath('data.debts.repaid', 0);

        // Bo'sh oraliq — hammasi nol
        $this->getJson('/api/v1/reports/overview?from=2020-01-01&to=2020-01-31')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 0)
            ->assertJsonPath('data.revenue', 0)
            ->assertJsonPath('data.net_profit', 0);

        $this->getJson('/api/v1/reports/overview?period=yearly')->assertStatus(422);
    }

    public function test_daily_series_covers_every_day_in_range(): void
    {
        $this->seedData();

        $days = $this->getJson('/api/v1/reports/daily?period=week')
            ->assertOk()
            ->assertJsonPath('data.from', today()->subDays(6)->toDateString())
            ->assertJsonPath('data.to', today()->toDateString())
            ->json('data.days');

        $this->assertCount(7, $days);
        $this->assertSame(today()->subDays(6)->toDateString(), $days[0]['date']);

        $todayRow = collect($days)->firstWhere('date', today()->toDateString());
        $this->assertSame(2, $todayRow['sales_count']);
        $this->assertSame(360000, (int) $todayRow['revenue']);
        $this->assertSame(30000, (int) $todayRow['expenses']);
        $this->assertSame(120000, (int) $todayRow['net_profit']);
        $this->assertSame(120000, (int) $todayRow['debt_given']);

        // Kechagi kun bo'sh, lekin seriyada mavjud
        $yesterday = collect($days)->firstWhere('date', today()->subDay()->toDateString());
        $this->assertSame(0, $yesterday['sales_count']);
        $this->assertSame(0, (int) $yesterday['revenue']);
    }

    public function test_top_products_ranked_and_net_of_returns(): void
    {
        $this->seedData();

        $pants = Product::factory()->for($this->user)->create([
            'name' => 'Shim',
            'buy_price' => 100000,
            'sell_price' => 180000,
            'stock' => 8,
            'min_stock' => 1,
        ]);

        $sale = $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [['product_id' => $pants->id, 'qty' => 2]],
        ])->assertCreated()->json('data');

        // 1 dona shim qaytarildi — hisobot netto ko'rsatishi kerak
        $this->postJson("/api/v1/sales/{$sale['id']}/return", [
            'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1]],
        ])->assertCreated();

        $items = $this->getJson('/api/v1/reports/top-products?period=day&by=revenue&limit=10')
            ->assertOk()
            ->assertJsonPath('data.by', 'revenue')
            ->json('data.items');

        $this->assertCount(2, $items);
        $this->assertSame('Futbolka', $items[0]['name']); // 3 dona = 360 000
        $this->assertSame(360000, (int) $items[0]['revenue']);
        $this->assertSame(3, (int) $items[0]['qty']);
        $this->assertSame('Shim', $items[1]['name']); // netto 1 dona = 180 000
        $this->assertSame(180000, (int) $items[1]['revenue']);
        $this->assertSame(80000, (int) $items[1]['profit']);

        // Foyda bo'yicha saralash
        $byProfit = $this->getJson('/api/v1/reports/top-products?period=day&by=profit')->assertOk()->json('data.items');
        $this->assertSame('Futbolka', $byProfit[0]['name']); // 150 000 > 80 000
    }

    public function test_reports_are_scoped_to_current_user(): void
    {
        $this->seedData();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cards.today_sales', 0)
            ->assertJsonPath('data.cards.debt_given', 0)
            ->assertJsonPath('data.today.sales_count', 0);

        $this->getJson('/api/v1/reports/top-products?period=day')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }
}
