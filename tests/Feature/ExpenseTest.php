<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function createExpense(array $overrides = []): int
    {
        return $this->postJson('/api/v1/expenses', $overrides + ['category' => 'rent', 'amount' => 100000])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_expense_crud_and_filters(): void
    {
        $rent = $this->createExpense(['note' => 'Sentabr ijara']);
        $this->createExpense(['category' => 'transport', 'amount' => 20000]);
        $this->createExpense(['category' => 'salary', 'amount' => 50000, 'spent_at' => today()->subDays(10)->toDateString()]);

        // Default davr — bugun.
        $this->getJson('/api/v1/expenses')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/expenses?period=month')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/expenses?period=all')->assertOk()->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/expenses?category=rent')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rent)
            ->assertJsonPath('data.0.note', 'Sentabr ijara')
            ->assertJsonPath('data.0.amount', 100000);

        $this->deleteJson("/api/v1/expenses/{$rent}")->assertOk();
        $this->getJson('/api/v1/expenses')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_summary_totals_and_categories(): void
    {
        $this->createExpense(['amount' => 100000]);
        $this->createExpense(['category' => 'transport', 'amount' => 20000]);
        $this->createExpense(['category' => 'transport', 'amount' => 30000, 'spent_at' => today()->subDays(3)->toDateString()]);

        // Default davr — bugun: ijara 100 000 + transport 20 000.
        $this->getJson('/api/v1/expenses/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 120000)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.by_category.0.category', 'rent')
            ->assertJsonPath('data.by_category.0.total', 100000);

        // Custom davr (TZ 17) — hammasi kiradi.
        $from = today()->subDays(6)->toDateString();
        $to = today()->toDateString();
        $this->getJson("/api/v1/expenses/summary?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('data.total', 150000)
            ->assertJsonPath('data.count', 3);
    }

    public function test_validation_and_user_isolation(): void
    {
        $this->postJson('/api/v1/expenses', ['category' => 'unknown', 'amount' => 1000])->assertStatus(422);
        $this->postJson('/api/v1/expenses', ['category' => 'rent', 'amount' => 0])->assertStatus(422);

        $expense = $this->createExpense();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/expenses')->assertOk()->assertJsonPath('meta.total', 0);
        $this->deleteJson("/api/v1/expenses/{$expense}")->assertNotFound();
    }

    public function test_client_uuid_makes_creation_idempotent(): void
    {
        $uuid = (string) Str::uuid();
        $first = $this->createExpense(['client_uuid' => $uuid]);
        $second = $this->createExpense(['client_uuid' => $uuid]);

        $this->assertSame($first, $second);
        $this->getJson('/api/v1/expenses')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
