<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * TZ 5, 17, 18: bosh sahifa (dashboard) kartalari va birlashtirilgan hisobotlar.
 *
 * Barcha summalar savdo sanasi bo'yicha netto hisoblanadi:
 * tushum = total − returned_total,
 * yalpi foyda = profit − (returned_total − returned_cost),
 * sof foyda = yalpi foyda − xarajatlar (TZ 14).
 */
class ReportController extends Controller
{
    use RespondsWithJson;

    /** GET /dashboard — bugungi kartalar, ogohlantirishlar va "Bugun nima bo'ldi?" (TZ 5, 18) */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = today()->toDateString();

        $sales = $this->salesTotals($request, $today, $today);
        $expenses = round((float) Expense::forUser($user)->between($today, $today)->sum('amount'), 2);
        [$debtGiven, $debtRepaid] = $this->debtTotals($request, $today, $today);

        $overdueDebts = Debt::forUser($user)->overdue();

        return $this->success([
            'date' => $today,
            // TZ 5: Bugungi savdo | Bugungi foyda | Berilgan qarz | Qaytgan qarz
            'cards' => [
                'today_sales' => $sales['revenue'],
                'today_profit' => $sales['gross_profit'],
                'debt_given' => $debtGiven,
                'debt_repaid' => $debtRepaid,
            ],
            // TZ 5: "3 ta qarz muddati o'tgan", "5 ta mahsulot kam qoldi"
            'alerts' => [
                'overdue_debts_count' => (clone $overdueDebts)->count(),
                'overdue_debts_total' => round((float) (clone $overdueDebts)->sum(DB::raw('amount - paid_amount')), 2),
                'low_stock_count' => Product::forUser($user)->active()->needsAttention()->count(),
            ],
            // TZ 18: kunlik qisqa xulosa
            'today' => [
                'sales_count' => $sales['count'],
                'revenue' => $sales['revenue'],
                'gross_profit' => $sales['gross_profit'],
                'expenses' => $expenses,
                'net_profit' => round($sales['gross_profit'] - $expenses, 2),
                'paid_cash' => $sales['paid_cash'],
                'paid_card' => $sales['paid_card'],
                'on_debt' => $sales['on_debt'],
                'debt_given' => $debtGiven,
                'debt_repaid' => $debtRepaid,
            ],
        ]);
    }

    /** GET /reports/overview?period=day|week|month|all yoki from=&to= (TZ 17) */
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'all'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        [$from, $to] = $this->range($data);

        $user = $request->user();
        $sales = $this->salesTotals($request, $from, $to);
        $expenses = round((float) Expense::forUser($user)->between($from, $to)->sum('amount'), 2);
        [$debtGiven, $debtRepaid] = $this->debtTotals($request, $from, $to);

        return $this->success([
            'from' => $from,
            'to' => $to,
            'sales_count' => $sales['count'],
            'revenue' => $sales['revenue'],
            'gross_profit' => $sales['gross_profit'],
            'expenses' => $expenses,
            'net_profit' => round($sales['gross_profit'] - $expenses, 2),
            'payments' => [
                'cash' => $sales['paid_cash'],
                'card' => $sales['paid_card'],
                'debt' => $sales['on_debt'],
            ],
            'debts' => [
                'given' => $debtGiven,
                'repaid' => $debtRepaid,
            ],
        ]);
    }

    /** GET /reports/daily?period=week|month yoki from=&to= — grafik uchun kunlik dinamika (TZ 17) */
    public function daily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['week', 'month'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        [$from, $to] = $this->range($data, defaultPeriod: 'month');
        $from ??= today()->subDays(29)->toDateString();
        $to ??= today()->toDateString();

        // Juda uzun oraliqni cheklaymiz — grafik uchun 92 kun yetarli
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 92) {
            $from = Carbon::parse($to)->subDays(92)->toDateString();
        }

        $user = $request->user();

        $sales = Sale::forUser($user)->between($from, $to)
            ->selectRaw('DATE(sold_at) as day')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total - returned_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(COALESCE(profit, 0) - (returned_total - returned_cost)), 0) as gross_profit')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $expenses = Expense::forUser($user)->between($from, $to)
            ->selectRaw('DATE(spent_at) as day, COALESCE(SUM(amount), 0) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $given = Debt::forUser($user)
            ->whereBetween('issued_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('DATE(issued_at) as day, COALESCE(SUM(amount), 0) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $repaid = DebtPayment::forUser($user)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('DATE(paid_at) as day, COALESCE(SUM(amount), 0) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $sale = $sales->get($day);
            $grossProfit = round((float) ($sale->gross_profit ?? 0), 2);
            $expense = round((float) ($expenses->get($day)->total ?? 0), 2);

            $days[] = [
                'date' => $day,
                'sales_count' => (int) ($sale->sales_count ?? 0),
                'revenue' => round((float) ($sale->revenue ?? 0), 2),
                'gross_profit' => $grossProfit,
                'expenses' => $expense,
                'net_profit' => round($grossProfit - $expense, 2),
                'debt_given' => round((float) ($given->get($day)->total ?? 0), 2),
                'debt_repaid' => round((float) ($repaid->get($day)->total ?? 0), 2),
            ];

            $cursor->addDay();
        }

        return $this->success(['from' => $from, 'to' => $to, 'days' => $days]);
    }

    /** GET /reports/top-products?period|from&to&by=revenue|qty|profit&limit= (TZ 17, 19) */
    public function topProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'all'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'by' => ['nullable', Rule::in(['revenue', 'qty', 'profit'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        [$from, $to] = $this->range($data, defaultPeriod: 'month');
        $by = $data['by'] ?? 'revenue';

        $items = SaleItem::query()
            ->where('sale_items.user_id', $request->user()->id)
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->when($from, fn ($q) => $q->where('sales.sold_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('sales.sold_at', '<=', $to.' 23:59:59'))
            ->selectRaw('sale_items.product_id, sale_items.name, MAX(sale_items.unit) as unit')
            ->selectRaw('COALESCE(SUM(sale_items.qty - sale_items.returned_qty), 0) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.price * (sale_items.qty - sale_items.returned_qty)), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN sale_items.buy_price IS NULL THEN 0 ELSE (sale_items.price - sale_items.buy_price) * (sale_items.qty - sale_items.returned_qty) END), 0) as profit')
            ->groupBy('sale_items.product_id', 'sale_items.name')
            ->orderByDesc($by)
            ->limit($data['limit'] ?? 10)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'unit' => $row->unit,
                'qty' => (float) $row->qty,
                'revenue' => round((float) $row->revenue, 2),
                'profit' => round((float) $row->profit, 2),
            ])
            ->values();

        return $this->success([
            'from' => $from,
            'to' => $to,
            'by' => $by,
            'items' => $items,
        ]);
    }

    /**
     * Davr bo'yicha savdo yig'indilari (netto, qaytarishlar ayirilgan).
     *
     * @return array{count: int, revenue: float, gross_profit: float, paid_cash: float, paid_card: float, on_debt: float}
     */
    private function salesTotals(Request $request, ?string $from, ?string $to): array
    {
        $row = Sale::forUser($request->user())
            ->between($from, $to)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total - returned_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(COALESCE(profit, 0) - (returned_total - returned_cost)), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(paid_cash), 0) as paid_cash')
            ->selectRaw('COALESCE(SUM(paid_card), 0) as paid_card')
            ->selectRaw('COALESCE(SUM(debt_amount), 0) as on_debt')
            ->first();

        return [
            'count' => (int) $row->sales_count,
            'revenue' => round((float) $row->revenue, 2),
            'gross_profit' => round((float) $row->gross_profit, 2),
            'paid_cash' => round((float) $row->paid_cash, 2),
            'paid_card' => round((float) $row->paid_card, 2),
            'on_debt' => round((float) $row->on_debt, 2),
        ];
    }

    /**
     * Davr bo'yicha berilgan va qaytgan qarz.
     *
     * @return array{0: float, 1: float}
     */
    private function debtTotals(Request $request, ?string $from, ?string $to): array
    {
        $user = $request->user();

        $given = Debt::forUser($user)
            ->when($from, fn ($q) => $q->where('issued_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('issued_at', '<=', $to.' 23:59:59'))
            ->sum('amount');

        $repaid = DebtPayment::forUser($user)
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to.' 23:59:59'))
            ->sum('amount');

        return [round((float) $given, 2), round((float) $repaid, 2)];
    }

    /**
     * `from`/`to` berilsa — o'sha oraliq; aks holda `period` dan hisoblanadi.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function range(array $data, string $defaultPeriod = 'day'): array
    {
        if (! empty($data['from']) || ! empty($data['to'])) {
            return [$data['from'] ?? null, $data['to'] ?? null];
        }

        return match ($data['period'] ?? $defaultPeriod) {
            'week' => [today()->subDays(6)->toDateString(), today()->toDateString()],
            'month' => [today()->subDays(29)->toDateString(), today()->toDateString()],
            'all' => [null, null],
            default => [today()->toDateString(), today()->toDateString()],
        };
    }
}
