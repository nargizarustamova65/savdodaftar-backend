<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TZ 22: bildirishnomalar — alohida jadval o'rniga (u V2 da) joriy
 * holatdan hosil qilinadi: muddati o'tgan/yaqinlashgan qarzlar va
 * kam qolgan yoki tugagan mahsulotlar.
 *
 * Tartib: subscription_expiring -> debt_overdue -> debt_due_soon -> out_of_stock/low_stock.
 * Matnlar mobil ilovada lokalizatsiya qilinadi — shu sababli javob
 * struktura ko'rinishida qaytadi.
 */
class NotificationController extends Controller
{
    use RespondsWithJson;

    /** GET /notifications?limit= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $data['limit'] ?? 50;
        $user = $request->user();
        $items = [];

        // TZ 22: Pro obuna muddati tugashiga 2-3 kun qolganda eslatma
        $subscription = $user->activeSubscription();

        if ($subscription !== null && $subscription->expires_at->lte(now()->addDays(3))) {
            $items[] = [
                'type' => 'subscription_expiring',
                'plan' => $subscription->plan,
                'expires_at' => $subscription->expires_at->toIso8601String(),
                'days_left' => (int) now()->startOfDay()->diffInDays($subscription->expires_at->copy()->startOfDay()),
            ];
        }

        foreach (Debt::forUser($user)->overdue()->with('customer')->orderBy('due_date')->limit($limit)->get() as $debt) {
            $items[] = [
                'type' => 'debt_overdue',
                'debt_id' => $debt->id,
                'name' => $debt->customer?->name,
                'amount' => $debt->remaining,
                'due_date' => $debt->due_date?->toDateString(),
            ];
        }

        foreach (Debt::forUser($user)->dueSoon(3)->with('customer')->orderBy('due_date')->limit($limit)->get() as $debt) {
            $items[] = [
                'type' => 'debt_due_soon',
                'debt_id' => $debt->id,
                'name' => $debt->customer?->name,
                'amount' => $debt->remaining,
                'due_date' => $debt->due_date?->toDateString(),
            ];
        }

        foreach (Product::forUser($user)->active()->needsAttention()->orderBy('stock')->limit($limit)->get() as $product) {
            $items[] = [
                'type' => $product->stockStatus() === Product::STOCK_OUT ? 'out_of_stock' : 'low_stock',
                'product_id' => $product->id,
                'name' => $product->name,
                'stock' => (float) $product->stock,
                'min_stock' => (float) $product->min_stock,
                'unit' => $product->unit,
            ];
        }

        $items = array_slice($items, 0, $limit);

        return $this->success([
            'count' => count($items),
            'items' => $items,
        ]);
    }
}
