<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDebtTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function createCustomer(array $overrides = []): int
    {
        return $this->postJson('/api/v1/customers', $overrides + ['name' => 'Ali aka', 'phone' => '90 123 45 67'])
            ->assertCreated()
            ->json('data.id');
    }

    private function createDebt(int $customerId, float $amount, array $overrides = []): int
    {
        return $this->postJson('/api/v1/debts', $overrides + ['customer_id' => $customerId, 'amount' => $amount])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_customer_crud_search_and_filters(): void
    {
        $ali = $this->createCustomer();
        $this->getJson("/api/v1/customers/{$ali}")->assertOk()->assertJsonPath('data.phone', '+998901234567');

        $this->putJson("/api/v1/customers/{$ali}", ['name' => 'Ali Valiyev'])->assertOk()->assertJsonPath('data.name', 'Ali Valiyev');

        $vali = $this->createCustomer(['name' => 'Bobur', 'phone' => null]);
        $this->createDebt($ali, 150000);

        $this->getJson('/api/v1/customers?filter=debtors')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Ali Valiyev')
            ->assertJsonPath('data.0.balance', 150000)
            ->assertJsonPath('data.0.is_debtor', true);

        $this->getJson('/api/v1/customers?search=Bobur')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/customers?search=901234567')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ali);

        $this->deleteJson("/api/v1/customers/{$ali}")->assertStatus(422)->assertJsonPath('code', 'customer_has_debt');

        $this->deleteJson("/api/v1/customers/{$vali}")->assertOk();
        $this->getJson("/api/v1/customers/{$vali}")->assertNotFound();
    }

    public function test_debt_increases_balance_and_payments_update_status(): void
    {
        $customer = $this->createCustomer();
        $debt = $this->createDebt($customer, 150000, ['note' => '2 ta futbolka', 'due_date' => '2026-09-20']);

        $this->getJson("/api/v1/customers/{$customer}")
            ->assertOk()
            ->assertJsonPath('data.balance', 150000)
            ->assertJsonPath('data.open_debts_count', 1);

        $this->postJson("/api/v1/debts/{$debt}/payments", ['amount' => 100000, 'payment_method' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('data.debt.status', 'partial')
            ->assertJsonPath('data.debt.remaining', 50000)
            ->assertJsonPath('data.debt.customer.balance', 50000);

        $this->postJson("/api/v1/debts/{$debt}/payments", ['amount' => 50000])
            ->assertCreated()
            ->assertJsonPath('data.debt.status', 'paid')
            ->assertJsonPath('data.debt.remaining', 0);

        $this->getJson("/api/v1/customers/{$customer}")->assertOk()->assertJsonPath('data.balance', 0)->assertJsonPath('data.is_debtor', false);

        $this->getJson("/api/v1/debts/{$debt}")->assertOk()->assertJsonCount(2, 'data.payments');

        $audit = $this->getJson("/api/v1/debts/{$debt}/audit")->assertOk()->json('data');
        $this->assertSame(['created', 'payment', 'payment'], array_column($audit, 'action'));
    }

    public function test_customer_payment_is_allocated_fifo_across_debts(): void
    {
        $customer = $this->createCustomer();
        $first = $this->createDebt($customer, 100000, ['issued_at' => '2026-09-01 10:00:00']);
        $second = $this->createDebt($customer, 200000, ['issued_at' => '2026-09-10 10:00:00']);

        $this->postJson("/api/v1/customers/{$customer}/payments", ['amount' => 150000])
            ->assertCreated()
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.customer.balance', 150000);

        $this->getJson("/api/v1/debts/{$first}")->assertJsonPath('data.status', 'paid');
        $this->getJson("/api/v1/debts/{$second}")->assertJsonPath('data.status', 'partial')->assertJsonPath('data.paid_amount', 50000);
    }

    public function test_overpayment_is_rejected(): void
    {
        $customer = $this->createCustomer();
        $debt = $this->createDebt($customer, 100000);

        $this->postJson("/api/v1/debts/{$debt}/payments", ['amount' => 150000])
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_exceeds_debt')
            ->assertJsonPath('meta.remaining', 100000);

        $this->postJson("/api/v1/customers/{$customer}/payments", ['amount' => 150000])
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_exceeds_debt');

        $this->postJson("/api/v1/debts/{$debt}/payments", ['amount' => 0])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_overdue_filter_and_summary(): void
    {
        $customer = $this->createCustomer();
        $this->createDebt($customer, 100000, ['due_date' => today()->subDay()->toDateString()]);
        $this->createDebt($customer, 50000, ['due_date' => today()->addDays(10)->toDateString()]);
        $this->createDebt($customer, 25000, ['due_date' => today()->addDay()->toDateString()]);

        $this->getJson('/api/v1/debts?status=overdue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('data.0.customer.name', 'Ali aka');

        $this->getJson('/api/v1/debts/summary')
            ->assertOk()
            ->assertJsonPath('data.total_outstanding', 175000)
            ->assertJsonPath('data.debtors_count', 1)
            ->assertJsonPath('data.overdue_count', 1)
            ->assertJsonPath('data.overdue_amount', 100000)
            ->assertJsonPath('data.due_soon_count', 1);
    }

    public function test_other_users_data_is_not_accessible(): void
    {
        $foreign = Customer::factory()->create();

        $this->getJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
        $this->postJson('/api/v1/debts', ['customer_id' => $foreign->id, 'amount' => 1000])->assertNotFound();
        $this->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_debt_deletion_rules(): void
    {
        $customer = $this->createCustomer();
        $paidDebt = $this->createDebt($customer, 100000);
        $this->postJson("/api/v1/debts/{$paidDebt}/payments", ['amount' => 10000])->assertCreated();

        $this->deleteJson("/api/v1/debts/{$paidDebt}")->assertStatus(422)->assertJsonPath('code', 'debt_has_payments');

        $openDebt = $this->createDebt($customer, 50000);
        $this->getJson("/api/v1/customers/{$customer}")->assertJsonPath('data.balance', 140000);

        $this->deleteJson("/api/v1/debts/{$openDebt}")->assertOk();
        $this->getJson("/api/v1/customers/{$customer}")->assertJsonPath('data.balance', 90000);
        $this->getJson("/api/v1/debts/{$openDebt}")->assertNotFound();
    }

    public function test_client_uuid_makes_creation_idempotent(): void
    {
        $customer = $this->createCustomer();
        $uuid = (string) Str::uuid();

        $first = $this->createDebt($customer, 100000, ['client_uuid' => $uuid]);
        $second = $this->createDebt($customer, 100000, ['client_uuid' => $uuid]);

        $this->assertSame($first, $second);
        $this->getJson("/api/v1/customers/{$customer}")->assertJsonPath('data.balance', 100000);
        $this->getJson('/api/v1/debts')->assertJsonPath('meta.total', 1);

        $customerUuid = (string) Str::uuid();
        $a = $this->createCustomer(['name' => 'Vali', 'client_uuid' => $customerUuid]);
        $b = $this->createCustomer(['name' => 'Vali', 'client_uuid' => $customerUuid]);
        $this->assertSame($a, $b);
    }

    public function test_customer_history_merges_debts_and_payments(): void
    {
        $customer = $this->createCustomer();
        $debt = $this->createDebt($customer, 150000, ['issued_at' => '2026-09-01 10:00:00']);
        $this->postJson("/api/v1/debts/{$debt}/payments", ['amount' => 100000])->assertCreated();

        $this->getJson("/api/v1/customers/{$customer}/history")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.type', 'payment')
            ->assertJsonPath('data.items.0.direction', 'minus')
            ->assertJsonPath('data.items.1.type', 'debt')
            ->assertJsonPath('data.items.1.remaining', 50000)
            ->assertJsonPath('data.customer.balance', 50000);
    }

    public function test_debt_due_date_and_note_can_be_updated(): void
    {
        $customer = $this->createCustomer();
        $debt = $this->createDebt($customer, 100000);

        $this->putJson("/api/v1/debts/{$debt}", ['due_date' => '2026-10-01', 'note' => 'Kechiktirildi'])
            ->assertOk()
            ->assertJsonPath('data.due_date', '2026-10-01')
            ->assertJsonPath('data.note', 'Kechiktirildi')
            ->assertJsonPath('data.amount', 100000);

        $audit = $this->getJson("/api/v1/debts/{$debt}/audit")->json('data');
        $this->assertSame('updated', end($audit)['action']);
    }
}
