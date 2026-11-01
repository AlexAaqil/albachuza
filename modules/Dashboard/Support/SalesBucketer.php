<?php

namespace Modules\Dashboard\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;

class SalesBucketer
{
    /** @return array{labels: string[], values: float[]} */
    public static function for(string $period, CarbonInterface $from, CarbonInterface $to): array
    {
        [$labels, $unit, $range] = self::spec($period, $to);
        $expr = self::part($unit);

        $rows = Order::completed()
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw("{$expr} as bucket, COALESCE(SUM(total_selling_price), 0) as total")
            ->groupBy(DB::raw($expr))
            ->pluck('total', 'bucket');

        $values = collect($range)
            ->map(fn ($k) => (float) ($rows[$k] ?? $rows[(string) $k] ?? 0))
            ->all();

        return ['labels' => $labels, 'values' => $values];
    }

    /** SQL expression returning an integer for the given date part. */
    private static function part(string $unit): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => match ($unit) {
                'hour'  => "CAST(strftime('%H', sold_at) AS INTEGER)",
                'dow'   => "CAST(strftime('%w', sold_at) AS INTEGER)",
                'day'   => "CAST(strftime('%d', sold_at) AS INTEGER)",
                'month' => "CAST(strftime('%m', sold_at) AS INTEGER)",
            },
            'pgsql' => match ($unit) {
                'hour'  => 'EXTRACT(HOUR FROM sold_at)::int',
                'dow'   => 'EXTRACT(DOW FROM sold_at)::int',   // Sun=0
                'day'   => 'EXTRACT(DAY FROM sold_at)::int',
                'month' => 'EXTRACT(MONTH FROM sold_at)::int',
            },
            default => match ($unit) {                          // mysql / mariadb
                'hour'  => 'HOUR(sold_at)',
                'dow'   => '(DAYOFWEEK(sold_at) - 1)',          // Sun=0
                'day'   => 'DAY(sold_at)',
                'month' => 'MONTH(sold_at)',
            },
        };
    }

    /** @return array{0: string[], 1: string, 2: int[]} */
    private static function spec(string $period, CarbonInterface $to): array
    {
        return match ($period) {
            'today' => [array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23)), 'hour', range(0, 23)],
            'week'  => [['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], 'dow', [1, 2, 3, 4, 5, 6, 0]],
            'month' => [array_map('strval', range(1, $to->daysInMonth)), 'day', range(1, $to->daysInMonth)],
            'year'  => [['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], 'month', range(1, 12)],
        };
    }
}