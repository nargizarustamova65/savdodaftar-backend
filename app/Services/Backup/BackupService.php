<?php

namespace App\Services\Backup;

use App\Exceptions\ApiException;
use App\Models\Backup;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * TZ 2, 23: bulutga zaxira nusxa va tiklash.
 *
 * Zaxira — foydalanuvchining barcha asosiy ma'lumotlarining JSON snapshot'i.
 * Mobil ilova payload'ni yuklab olib, lokal bazani tiklaydi (offline-first).
 */
class BackupService
{
    public const VERSION = 1;

    public function create(User $user, string $source = Backup::SOURCE_MANUAL): Backup
    {
        $data = [
            'customers' => Customer::forUser($user)->get()->toArray(),
            'debts' => Debt::forUser($user)->get()->toArray(),
            'debt_payments' => DebtPayment::forUser($user)->get()->toArray(),
            'products' => Product::forUser($user)->get()->toArray(),
            'stock_movements' => StockMovement::forUser($user)->get()->toArray(),
            'sales' => Sale::forUser($user)->with('items')->get()->toArray(),
            'sale_returns' => SaleReturn::forUser($user)->with('items')->get()->toArray(),
            'expenses' => Expense::forUser($user)->get()->toArray(),
        ];

        $counts = collect($data)->map(fn (array $rows) => count($rows))->all();

        $payload = [
            'version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'shop_name' => $user->shop_name,
            ],
            'counts' => $counts,
            'data' => $data,
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $path = sprintf('backups/%d/%s_%s.json', $user->id, now()->format('Ymd_His'), Str::lower(Str::random(8)));

        Storage::disk($this->disk())->put($path, $json);

        $backup = Backup::create([
            'user_id' => $user->id,
            'path' => $path,
            'size' => strlen($json),
            'checksum' => hash('sha256', $json),
            'counts' => $counts,
            'source' => $source,
        ]);

        $this->prune($user);

        return $backup;
    }

    /** Tiklash uchun zaxira faylining to'liq payload'i */
    public function payload(Backup $backup): array
    {
        $storage = Storage::disk($this->disk());

        if (! $storage->exists($backup->path)) {
            throw new ApiException(__('messages.backup.file_missing'), 410, 'backup_file_missing');
        }

        return (array) json_decode((string) $storage->get($backup->path), true);
    }

    public function delete(Backup $backup): void
    {
        Storage::disk($this->disk())->delete($backup->path);
        $backup->delete();
    }

    /** Har bir foydalanuvchi uchun faqat oxirgi N ta zaxira saqlanadi */
    private function prune(User $user): void
    {
        Backup::forUser($user)
            ->orderByDesc('id')
            ->skip($this->keep())
            ->take(100)
            ->get()
            ->each(fn (Backup $old) => $this->delete($old));
    }

    private function disk(): string
    {
        return (string) config('savdodaftar.backup.disk');
    }

    private function keep(): int
    {
        return max(1, (int) config('savdodaftar.backup.keep'));
    }
}
