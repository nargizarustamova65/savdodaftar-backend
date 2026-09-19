<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('savdodaftar.backup.disk', 'local');

        $this->user = User::factory()->create(['shop_name' => 'Bozor Market']);
        Sanctum::actingAs($this->user);
    }

    private function seedData(): void
    {
        $product = Product::factory()->for($this->user)->create([
            'name' => 'Futbolka',
            'buy_price' => 70000,
            'sell_price' => 120000,
            'stock' => 50,
            'min_stock' => 5,
        ]);

        Customer::factory()->for($this->user)->create(['name' => 'Ali aka']);

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ])->assertCreated();

        $this->postJson('/api/v1/expenses', ['category' => 'transport', 'amount' => 30000])->assertCreated();
    }

    public function test_create_list_download_and_delete_backup(): void
    {
        $this->seedData();

        $backup = $this->postJson('/api/v1/backups')
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.counts.customers', 1)
            ->assertJsonPath('data.counts.products', 1)
            ->assertJsonPath('data.counts.sales', 1)
            ->assertJsonPath('data.counts.expenses', 1)
            ->json('data');

        $this->assertGreaterThan(0, $backup['size']);
        $this->assertNotEmpty($backup['checksum']);

        Storage::disk('local')->assertExists(Backup::findOrFail($backup['id'])->path);

        $this->getJson('/api/v1/backups')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.id', $backup['id']);

        // Tiklash uchun payload
        $this->getJson("/api/v1/backups/{$backup['id']}")
            ->assertOk()
            ->assertJsonPath('data.payload.version', 1)
            ->assertJsonPath('data.payload.user.shop_name', 'Bozor Market')
            ->assertJsonPath('data.payload.data.products.0.name', 'Futbolka')
            ->assertJsonPath('data.payload.data.customers.0.name', 'Ali aka')
            ->assertJsonPath('data.payload.counts.sales', 1)
            ->assertJsonPath('data.payload.data.sales.0.items.0.name', 'Futbolka');

        $path = Backup::findOrFail($backup['id'])->path;
        $this->deleteJson("/api/v1/backups/{$backup['id']}")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->getJson('/api/v1/backups')->assertJsonPath('data.count', 0);
    }

    public function test_retention_keeps_only_last_n_backups(): void
    {
        config()->set('savdodaftar.backup.keep', 2);
        $this->seedData();

        $first = $this->postJson('/api/v1/backups')->assertCreated()->json('data.id');
        $firstPath = Backup::findOrFail($first)->path;

        $this->postJson('/api/v1/backups')->assertCreated();
        $this->postJson('/api/v1/backups')->assertCreated();

        $this->getJson('/api/v1/backups')->assertJsonPath('data.count', 2);
        $this->assertNull(Backup::find($first));
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_backups_are_scoped_to_user(): void
    {
        $this->seedData();
        $id = $this->postJson('/api/v1/backups')->assertCreated()->json('data.id');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/backups')->assertOk()->assertJsonPath('data.count', 0);
        $this->getJson("/api/v1/backups/{$id}")->assertNotFound();
        $this->deleteJson("/api/v1/backups/{$id}")->assertNotFound();

        $this->assertNotNull(Backup::find($id));
    }
}
