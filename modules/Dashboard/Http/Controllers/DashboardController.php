<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Dashboard\Support\PeriodStats;
use Modules\Dashboard\Support\SalesBucketer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Composer\InstalledVersions;
use Dotenv\Dotenv;
use Modules\Order\Enums\OrderStatusEnum;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\User\Enums\UserRoles;
use Modules\User\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (in_array($user->role, [UserRoles::SUPER_ADMIN, UserRoles::ADMIN], true)) {
            $isSuper = $user->role === UserRoles::SUPER_ADMIN;
            $view    = $isSuper ? 'app/dashboards/SuperAdmin' : 'app/dashboards/Admin';

            $stats = $this->adminStats($request->query('period', 'month'));

            // Only Super Admins get system stats; Admins never receive the payload.
            if ($isSuper) {
                $stats['system'] = $this->systemStats();
            }

            return inertia($view, [
                'user'  => $user,
                'stats' => $stats,
            ]);
        }

        if ($user->role === UserRoles::CASHIER) {
            return inertia('app/dashboards/Cashier', [
                'user'  => $user,
                'stats' => $this->cashierStats($user->id),
            ]);
        }

        if ($user->role === UserRoles::CUSTOMER) {
            return inertia('app/dashboards/Customer', [
                'user'  => $user,
                'stats' => [],
            ]);
        }

        return inertia('app/dashboards/Dashboard');
    }

    private function ranges(CarbonImmutable $now): array
    {
        return [
            'today'      => [$now->startOfDay(),   $now->endOfDay()],
            'yesterday'  => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'week'       => [$now->startOfWeek(),  $now->endOfWeek()],
            'last_week'  => [$now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()],
            'month'      => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'year'       => [$now->startOfYear(),  $now->endOfYear()],
            'last_year'  => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
        ];
    }

    private function deltaSet(array $cur, array $prev): array
    {
        return [
            'revenue'    => PeriodStats::delta($cur['revenue'],    $prev['revenue']),
            'orders'     => PeriodStats::delta($cur['orders'],     $prev['orders']),
            'expenses'   => PeriodStats::delta($cur['expenses'],   $prev['expenses']),
            'net_profit' => PeriodStats::delta($cur['net_profit'], $prev['net_profit']),
        ];
    }

    /**
     * Shared payload for SUPER_ADMIN + ADMIN.
     */
    private function adminStats(string $period): array
    {
        $period = in_array($period, ['today', 'week', 'month', 'year'], true) ? $period : 'month';

        $ranges   = $this->ranges(CarbonImmutable::now());
        $previous = [
            'today' => 'yesterday', 'week' => 'last_week',
            'month' => 'last_month', 'year' => 'last_year',
        ];

        // Only compute what the page actually uses (4 instead of 8 PeriodStats runs).
        $p = [];
        foreach (array_unique(['today', 'yesterday', $period, $previous[$period]]) as $key) {
            [$from, $to] = $ranges[$key];
            $p[$key] = PeriodStats::for($from, $to);
        }

        $deltas = [
            'today'  => $this->deltaSet($p['today'], $p['yesterday']),
            $period  => $this->deltaSet($p[$period], $p[$previous[$period]]),
        ];

        [$chartFrom, $chartTo] = $ranges[$period];
        $trend = SalesBucketer::for($period, $chartFrom, $chartTo);

        $completedIds = Order::completed()
            ->whereBetween('sold_at', [$chartFrom, $chartTo])
            ->select('orders.id');

        $paymentSplit = Payment::query()
            ->where('payment_status', 'paid')
            ->whereIn('payment_method', ['mpesa', 'cash'])
            ->whereIn('order_id', $completedIds)
            ->selectRaw('payment_method, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $statusMix = Order::query()
            ->whereBetween('sold_at', [$chartFrom, $chartTo])
            ->selectRaw('order_status, COUNT(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        // Group on the order-time snapshot so deleted products still show up.
        $topProducts = DB::table('order_items')
            ->whereIn('order_id', $completedIds)
            ->selectRaw('
                order_items.product_id as id,
                order_items.product_name as name,
                SUM(order_items.quantity - order_items.returned_quantity) as qty,
                SUM(order_items.total) as revenue
            ')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'      => $r->id,
                'name'    => $r->name,
                'qty'     => (float) $r->qty,
                'revenue' => (float) $r->revenue,
            ]);

        $counts = [
            'total_users'             => User::where('role', '!=', UserRoles::SUPER_ADMIN)->count(),
            'total_admins'            => User::where('role', '=', UserRoles::ADMIN)->count(),
            'total_cashiers'          => User::where('role', '=', UserRoles::CASHIER)->count(),
            'total_products'          => Product::count(),
            'total_product_categories'=> ProductCategory::count(),
            'total_orders'            => Order::count(),
            'orders_need_attention'   => Order::needsAttention()->count(),
        ];

        return [
            'period'  => $period,
            'periods' => ['today', 'week', 'month', 'year'],

            'glance' => [
                'today' => [
                    'revenue'    => $p['today']['revenue'],
                    'expenses'   => $p['today']['expenses'],
                    'net_profit' => $p['today']['net_profit'],
                    'net_margin' => $p['today']['net_margin'],
                    'orders'     => $p['today']['orders'],
                    'delta'      => $deltas['today'],
                ],
                'needs_attention' => $counts['orders_need_attention'],
                'low_stock_count' => Product::query()
                    ->where('is_active', true)->where('track_inventory', true)
                    ->whereColumn('current_stock', '<=', 'low_stock_threshold')
                    ->count(),
            ],

            'trend' => ['labels' => $trend['labels'], 'values' => $trend['values']],

            'payment_breakdown' => [
                'mpesa' => (float) ($paymentSplit['mpesa'] ?? 0),
                'cash'  => (float) ($paymentSplit['cash']  ?? 0),
            ],
            'status_mix'   => $statusMix->map(fn ($v) => (int) $v),
            'top_products' => $topProducts,

            // Includes expenses, net_profit, net_margin from PeriodStats
            'fiscal'            => $p[$period] + ['delta' => $deltas[$period]],
            'expense_breakdown' => PeriodStats::expenseBreakdown($chartFrom, $chartTo),

            'deltas' => $deltas,
            'counts' => $counts,
        ];
    }

    /**
     * Cashier payload — unchanged logic, just extracted.
     */
    private function cashierStats(int $userId): array
    {
        $today = now()->startOfDay();

        $order_summary = Order::query()
            ->where('created_by', $userId)
            ->where('sold_at', '>=', $today)
            ->selectRaw('
                COUNT(*) as orders_count,
                COALESCE(SUM(CASE WHEN order_status != ? THEN total_selling_price ELSE 0 END), 0) as sales_total,
                SUM(CASE WHEN order_status IN (?, ?) AND amount_paid < total_selling_price THEN 1 ELSE 0 END) as pending_payment,
                SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as ready_for_pickup
            ', [
                OrderStatusEnum::CANCELLED->value,
                OrderStatusEnum::PENDING->value,
                OrderStatusEnum::PROCESSING->value,
                OrderStatusEnum::READY_FOR_PICKUP->value,
            ])
            ->first();

        $payment_breakdown = Payment::query()
            ->whereIn('order_id', function ($q) use ($userId, $today) {
                $q->select('id')
                    ->from('orders')
                    ->where('created_by', $userId)
                    ->where('sold_at', '>=', $today);
            })
            ->where('payment_status', 'paid')
            ->whereIn('payment_method', ['mpesa', 'cash'])
            ->selectRaw('payment_method, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $lowStock = Product::query()
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->orderBy('current_stock')
            ->limit(5)
            ->get(['id', 'name', 'current_stock'])
            ->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'current_stock' => (float) $p->current_stock,
            ]);

        return [
            'today' => [
                'orders_count'    => (int) $order_summary->orders_count,
                'sales_total'     => (float) $order_summary->sales_total,
                'cash_collected'  => (float) ($payment_breakdown['cash']  ?? 0),
                'mpesa_collected' => (float) ($payment_breakdown['mpesa'] ?? 0),
            ],
            'needs_attention' => [
                'pending_payment'  => (int) $order_summary->pending_payment,
                'ready_for_pickup' => (int) $order_summary->ready_for_pickup,
            ],
            'low_stock' => $lowStock,
        ];
    }

    private function systemStats(): array
    {
        $ver = fn (string $pkg) => InstalledVersions::isInstalled($pkg)
            ? InstalledVersions::getPrettyVersion($pkg)
            : null;

        $packages = array_filter([
            'laravel/framework'          => $ver('laravel/framework'),
            'inertiajs/inertia-laravel'  => $ver('inertiajs/inertia-laravel'),
            'intervention/image'         => $ver('intervention/image'),
        ]);

        // What .env on disk says vs what the running app is actually using.
        $envFile = [];
        if (is_readable(base_path('.env'))) {
            try {
                $envFile = Dotenv::parse(file_get_contents(base_path('.env')));
            } catch (\Throwable) {}
        }

        $warnings = [];
        if (isset($envFile['APP_ENV']) && $envFile['APP_ENV'] !== app()->environment()) {
            $warnings[] = ".env says APP_ENV={$envFile['APP_ENV']} but the app is running as "
                . app()->environment() . '. Run `php artisan config:clear` (or config:cache) and restart PHP-FPM/workers, '
                . 'or check for a server-level APP_ENV override.';
        }
        if (isset($envFile['APP_DEBUG'])
            && filter_var($envFile['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) !== (bool) config('app.debug')) {
            $warnings[] = '.env APP_DEBUG differs from the running config (stale cache or override).';
        }
        if (app()->isProduction() && config('app.debug')) {
            $warnings[] = 'APP_DEBUG is ON in production.';
        }

        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        return [
            'app' => [
                'name'          => config('app.name'),
                'env'           => app()->environment(),
                'debug'         => (bool) config('app.debug'),
                'url'           => config('app.url'),
                'timezone'      => config('app.timezone'),
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
                'maintenance'   => app()->isDownForMaintenance(),
            ],
            'drivers' => [
                'cache'   => config('cache.default'),
                'queue'   => config('queue.default'),
                'session' => config('session.driver'),
                'mail'    => config('mail.default'),
            ],
            'warnings' => $warnings,
            'versions' => [
                'laravel' => app()->version(),
                'php'     => PHP_VERSION,
                'sapi'    => PHP_SAPI,
            ],
            'runtime' => [                       // per-request values, label them as such in the UI
                'memory_usage' => memory_get_usage(true),
                'memory_peak'  => memory_get_peak_usage(true),
                'opcache'      => (bool) ($opcache['opcache_enabled'] ?? false),
            ],
            'packages' => $packages,
            'server'   => [
                'os'       => PHP_OS_FAMILY,
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            ],
            'database' => $this->databaseHealth(),
        ];
    }

    private function databaseHealth(): array
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');

        try {
            $start = microtime(true);
            DB::select('SELECT 1');

            return [
                'ok'      => true,
                'driver'  => $driver,
                'ping_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'driver' => $driver, 'error' => 'Connection failed (see logs).'];
        }
    }
}