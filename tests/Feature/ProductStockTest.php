<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductStockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function createProduct(array $overrides = []): int
    {
        return $this->postJson('/api/v1/products', $overrides + [
            'name' => 'Futbolka',
            'category' => 'Kiyim',
            'buy_price' => 70000,
            'sell_price' => 120000,
            'stock' => 50,
            'min_stock' => 5,
        ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_product_is_created_with_initial_stock_and_margin(): void
    {
        $id = $this->createProduct();

        $this->getJson("/api/v1/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Futbolka')
            ->assertJsonPath('data.unit', 'dona')
            ->assertJsonPath('data.stock', 50)
            ->assertJsonPath('data.margin', 50000)
            ->assertJsonPath('data.margin_percent', 71.4)
            ->assertJsonPath('data.stock_status', 'ok')
            ->assertJsonPath('data.stock_value', 3500000)
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.type', 'initial')
            ->assertJsonPath('data.movements.0.stock_after', 50);

        $this->putJson("/api/v1/products/{$id}", ['sell_price' => 130000, 'min_stock' => 10])
            ->assertOk()
            ->assertJsonPath('data.sell_price', 130000)
            ->assertJsonPath('data.min_stock', 10)
            ->assertJsonPath('data.stock', 50);

        $audit = $this->getJson("/api/v1/products/{$id}/audit")->assertOk()->json('data');
        $this->assertSame(['created', 'updated'], array_column($audit, 'action'));
        $this->assertSame(120000.0, (float) $audit[1]['old_values']['sell_price']);
        $this->assertSame(130000.0, (float) $audit[1]['new_values']['sell_price']);
    }

    public function test_stock_in_and_out_update_stock_and_record_movements(): void
    {
        $id = $this->createProduct(['stock' => 12]);

        $this->postJson("/api/v1/products/{$id}/stock-in", ['qty' => 30, 'buy_price' => 75000])
            ->assertCreated()
            ->assertJsonPath('data.product.stock', 42)
            ->assertJsonPath('data.product.buy_price', 75000)
            ->assertJsonPath('data.movement.type', 'in')
            ->assertJsonPath('data.movement.direction', 'plus')
            ->assertJsonPath('data.movement.qty', 30)
            ->assertJsonPath('message', 'Kirim saqlandi. Yangi qoldiq: 42 dona.');

        $this->postJson("/api/v1/products/{$id}/stock-out", ['qty' => 2, 'note' => 'Buzilgan'])
            ->assertCreated()
            ->assertJsonPath('data.product.stock', 40)
            ->assertJsonPath('data.movement.direction', 'minus')
            ->assertJsonPath('data.movement.signed_qty', -2);

        $this->getJson("/api/v1/products/{$id}/movements")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.type', 'out')
            ->assertJsonPath('data.2.type', 'initial');

        $this->getJson('/api/v1/inventory/movements?type=in')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product.name', 'Futbolka');

        // Kirimdagi tannarx o'zgarishi narx tarixiga yoziladi
        $audit = $this->getJson("/api/v1/products/{$id}/audit")->json('data');
        $this->assertSame(75000.0, (float) end($audit)['new_values']['buy_price']);
    }

    public function test_stock_out_more_than_available_is_rejected(): void
    {
        $id = $this->createProduct(['stock' => 3]);

        $this->postJson("/api/v1/products/{$id}/stock-out", ['qty' => 5])
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('meta.available', 3)
            ->assertJsonPath('meta.requested', 5);

        $this->getJson("/api/v1/products/{$id}")->assertJsonPath('data.stock', 3);

        $this->postJson("/api/v1/products/{$id}/stock-in", ['qty' => 0])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_adjust_and_bulk_inventory_count(): void
    {
        $a = $this->createProduct(['stock' => 100]);
        $b = $this->createProduct(['name' => 'Shim', 'stock' => 20]);

        $this->postJson("/api/v1/products/{$a}/adjust", ['actual_stock' => 96, 'note' => 'Sanash'])
            ->assertCreated()
            ->assertJsonPath('data.product.stock', 96)
            ->assertJsonPath('data.movement.type', 'adjustment')
            ->assertJsonPath('data.movement.signed_qty', -4)
            ->assertJsonPath('message', 'Qoldiq tuzatildi. Farq: -4 dona.');

        $this->postJson("/api/v1/products/{$a}/adjust", ['actual_stock' => 96])
            ->assertOk()
            ->assertJsonPath('data.movement', null);

        $this->postJson('/api/v1/inventory/count', [
            'items' => [
                ['product_id' => $a, 'actual_stock' => 90],
                ['product_id' => $b, 'actual_stock' => 25],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.adjusted_count', 2)
            ->assertJsonPath('data.shortage_total', -6)
            ->assertJsonPath('data.surplus_total', 5)
            ->assertJsonPath('data.items.0.previous_stock', 96)
            ->assertJsonPath('data.items.0.difference', -6)
            ->assertJsonPath('data.items.1.product.stock', 25);
    }

    public function test_filters_summary_categories_and_barcode_lookup(): void
    {
        $this->createProduct(['name' => 'Futbolka', 'stock' => 12, 'min_stock' => 5, 'barcode' => '4780000000011']);
        $low = $this->createProduct(['name' => 'Ko\'ylak', 'stock' => 5, 'min_stock' => 5]);
        $this->createProduct(['name' => 'Krossovka', 'category' => 'Poyabzal', 'stock' => 0, 'min_stock' => 2, 'buy_price' => null]);
        $this->createProduct(['name' => 'Eski model', 'stock' => 7, 'is_active' => false]);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/products?filter=low_stock')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $low)->assertJsonPath('data.0.stock_status', 'low');
        $this->getJson('/api/v1/products?filter=out_of_stock')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.stock_status', 'out');
        $this->getJson('/api/v1/products?filter=attention')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/products?filter=inactive')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/products?category=Poyabzal')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/products?search=kross')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/products?search=4780000000011')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Futbolka');

        $this->getJson('/api/v1/products/summary')
            ->assertOk()
            ->assertJsonPath('data.total_products', 3)
            ->assertJsonPath('data.low_stock_count', 1)
            ->assertJsonPath('data.out_of_stock_count', 1)
            ->assertJsonPath('data.attention_count', 2)
            ->assertJsonPath('data.stock_value', 1190000)
            ->assertJsonPath('data.potential_revenue', 2040000);

        $categories = $this->getJson('/api/v1/products/categories')->assertOk()->json('data');
        $this->assertSame([['name' => 'Kiyim', 'products_count' => 2], ['name' => 'Poyabzal', 'products_count' => 1]], $categories);

        $this->getJson('/api/v1/products/barcode/4780000000011')->assertOk()->assertJsonPath('data.name', 'Futbolka');
        $this->getJson('/api/v1/products/barcode/0000')->assertNotFound()->assertJsonPath('code', 'product_not_found');
    }

    public function test_barcode_must_be_unique_per_user(): void
    {
        $first = $this->createProduct(['barcode' => '111']);

        $this->postJson('/api/v1/products', ['name' => 'Boshqa', 'sell_price' => 1000, 'barcode' => '111'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'barcode_taken')
            ->assertJsonPath('meta.product_id', $first);

        $second = $this->createProduct(['name' => 'Shim', 'barcode' => '222']);
        $this->putJson("/api/v1/products/{$second}", ['barcode' => '111'])->assertStatus(422)->assertJsonPath('code', 'barcode_taken');
        $this->putJson("/api/v1/products/{$second}", ['barcode' => '  '])->assertOk()->assertJsonPath('data.barcode', null);

        // Boshqa foydalanuvchi bir xil barcode ishlatishi mumkin
        Product::factory()->create(['barcode' => '111']);
        $this->getJson('/api/v1/products')->assertJsonPath('meta.total', 2);
    }

    public function test_client_uuid_makes_product_and_movement_idempotent(): void
    {
        $uuid = (string) Str::uuid();
        $a = $this->createProduct(['client_uuid' => $uuid, 'stock' => 10]);
        $b = $this->createProduct(['client_uuid' => $uuid, 'stock' => 10]);

        $this->assertSame($a, $b);
        $this->getJson("/api/v1/products/{$a}")->assertJsonPath('data.stock', 10);

        $moveUuid = (string) Str::uuid();
        $this->postJson("/api/v1/products/{$a}/stock-in", ['qty' => 5, 'client_uuid' => $moveUuid])->assertCreated();
        $this->postJson("/api/v1/products/{$a}/stock-in", ['qty' => 5, 'client_uuid' => $moveUuid])->assertCreated();

        $this->getJson("/api/v1/products/{$a}")->assertJsonPath('data.stock', 15);
        $this->getJson("/api/v1/products/{$a}/movements")->assertJsonPath('meta.total', 2);
    }

    public function test_other_users_products_are_not_accessible(): void
    {
        $foreign = Product::factory()->create();

        $this->getJson("/api/v1/products/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/products/{$foreign->id}/stock-in", ['qty' => 1])->assertNotFound();
        $this->postJson('/api/v1/inventory/count', ['items' => [['product_id' => $foreign->id, 'actual_stock' => 1]]])->assertNotFound();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_product_can_be_soft_deleted_and_image_uploaded(): void
    {
        Storage::fake('public');
        $id = $this->createProduct();

        $this->post("/api/v1/products/{$id}/image", ['image' => UploadedFile::fake()->create('futbolka.jpg', 120, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.image_url', fn ($url) => is_string($url) && str_contains($url, 'products/'.$this->user->id.'/'));

        $path = Product::find($id)->image_path;
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/v1/products/{$id}/image")->assertOk()->assertJsonPath('data.image_url', null);
        Storage::disk('public')->assertMissing($path);

        $this->deleteJson("/api/v1/products/{$id}")->assertOk();
        $this->getJson("/api/v1/products/{$id}")->assertNotFound();
        $this->assertSoftDeleted('products', ['id' => $id]);
    }
}
