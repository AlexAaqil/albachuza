<?php

namespace Modules\Dashboard\Support;

use Carbon\CarbonInterface;
use Modules\Order\Models\Order;
use Modules\Expense\Models\Expense;

class PeriodStats
{
    /**
     * Compute completed-order aggregates for a date range.
     */
    public static function for(CarbonInterface $from, CarbonInterface $to): array
    {
        $summary = Order::completed()
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total_selling_price), 0) as revenue,
                COALESCE(SUM(total_cost_price), 0) as cogs
            ')
            ->first();

        $orders      = (int) $summary->count;
        $revenue     = (float) $summary->revenue;
        $cogs        = (float) $summary->cogs;
        $grossProfit = $revenue - $cogs;

        // Operating expenses booked against this period.
        // expense_date is a DATE column, so compare against date strings.
        $expenses = (float) Expense::query()
            ->whereBetween('expense_date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->sum('amount');

        $netProfit = $grossProfit - $expenses;

        return [
            'orders'       => $orders,
            'revenue'      => $revenue,
            'cogs'         => $cogs,
            'gross_profit' => $grossProfit,
            'expenses'     => $expenses,
            'net_profit'   => $netProfit,
            'margin'       => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0.0,
            'net_margin'   => $revenue > 0 ? ($netProfit  / $revenue) * 100 : 0.0,
            'aov'          => $orders > 0 ? $revenue / $orders : 0.0,
        ];
    }

    /**
     * Category-level expense breakdown for a single period.
     * Call this once for the selected period only — not 8×.
     *
     * @return array<string, float>
     */
    public static function expenseBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        return Expense::query()
            ->whereBetween('expense_date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw("
                COALESCE(expense_categories.name, 'Uncategorized') as name,
                SUM(expenses.amount) as total
            ")
            ->groupBy('name')
            ->orderByDesc('total')
            ->pluck('total', 'name')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Percentage change between two values, safe against zero baselines.
     */
    public static function delta(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : ($current > 0 ? 100.0 : -100.0);
        }

        // abs() keeps the sign right when the baseline is negative (e.g. net loss last month)
        return (($current - $previous) / abs($previous)) * 100;
    }
}