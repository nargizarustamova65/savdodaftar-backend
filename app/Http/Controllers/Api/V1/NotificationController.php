<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Debt;
use App\Models\NotificationRead;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->input('limit', 50)));
        $user = $request->user();
        $reads = NotificationRead::where('user_id', $user->id)->pluck('read_at', 'notification_id');
        $items = [];

        foreach (AdminNotification::query()->where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->latest()->limit($limit)->get() as $notification) {
            $items[] = [
                'id' => 'admin_'.$notification->id,
                'source_id' => $notification->id,
                'type' => 'admin',
                'title' => $notification->title,
                'body' => $notification->body,
                'created_at' => $notification->created_at?->toIso8601String(),
                'is_read' => $reads->has($notification->id),
            ];
        }

        $subscription = $user->activeSubscription();
        if ($subscription !== null && $subscription->expires_at->lte(now()->addDays(3))) {
            $items[] = ['id' => 'subscription_expiring', 'type' => 'subscription_expiring', 'plan' => $subscription->plan, 'expires_at' => $subscription->expires_at->toIso8601String(), 'days_left' => max(0, (int) now()->startOfDay()->diffInDays($subscription->expires_at->copy()->startOfDay())), 'is_read' => false];
        }
        foreach (Debt::forUser($user)->overdue()->with('customer')->orderBy('due_date')->limit($limit)->get() as $debt) {
            $items[] = ['id' => 'debt_overdue_'.$debt->id, 'type' => 'debt_overdue', 'debt_id' => $debt->id, 'name' => $debt->customer?->name, 'amount' => $debt->remaining, 'due_date' => $debt->due_date?->toDateString(), 'is_read' => false];
        }
        foreach (Product::forUser($user)->active()->needsAttention()->orderBy('stock')->limit($limit)->get() as $product) {
            $items[] = ['id' => 'stock_'.$product->id, 'type' => $product->stockStatus() === Product::STOCK_OUT ? 'out_of_stock' : 'low_stock', 'product_id' => $product->id, 'name' => $product->name, 'stock' => (float) $product->stock, 'min_stock' => (float) $product->min_stock, 'unit' => $product->unit, 'is_read' => false];
        }
        return $this->success(['count' => count($items), 'unread_count' => count(array_filter($items, fn ($item) => !($item['is_read'] ?? false))), 'items' => array_slice($items, 0, $limit)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = AdminNotification::whereNull('user_id')->orWhere('user_id', $user->id)->pluck('id');
        foreach ($ids as $id) NotificationRead::updateOrCreate(['notification_id' => $id, 'user_id' => $user->id], ['read_at' => now()]);
        return $this->success(message: 'Bildirishnomalar o‘qildi.');
    }
}
