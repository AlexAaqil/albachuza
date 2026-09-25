<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\DB;
use Modules\User\Enums\UserRoles;
use Modules\User\Models\User;
// use Modules\Product\Models\Product;
// use Modules\Product\Models\ProductCategory;
// use Modules\Order\Enums\OrderStatusEnum;
// use Modules\Order\Models\Order;
// use Modules\Payment\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->role === UserRoles::SUPER_ADMIN) {
            return inertia('app/dashboards/SuperAdmin', [
                'user' => $user,
                'stats' => [
                    'total_users' => User::where('role', '!=', UserRoles::SUPER_ADMIN)->count(),
                    'total_admins' => User::where('role', '=', UserRoles::ADMIN)->count(),

                    'total_products' => 0,
                    'total_product_categories' => 0,

                    'total_orders' => 0,
                    'orders_need_attention' => 0,

                    'monthly_sales' => 0,
                    'payment_breakdown' => [
                        'mpesa' => (float) 0,
                        'cash' => (float) 0,
                    ],
                    'total_revenue' => (float) 0,
                    'total_cogs' => (float) 0,
                    'total_gross_profit' => (float) 0,
                    'gross_profit_margin' => (float) 0,
                    'aov' => (float) 0,
                ]
            ]);
        }

        if ($user->role === UserRoles::ADMIN) {
            return inertia('app/dashboards/Admin', [
                'user' => $user,
                'stats' => [
                    'total_users' => User::where('role', '!=', UserRoles::SUPER_ADMIN)->count(),
                    'total_admins' => User::where('role', '=', UserRoles::ADMIN)->count(),
                    'total_cashiers' => User::where('role', '=', UserRoles::CASHIER)->count(),

                    'total_products' => 0,
                    'total_product_categories' => 0,

                    'total_orders' => 0,
                    'orders_need_attention' => 0,

                    'monthly_sales' =>0,
                    'payment_breakdown' => [
                        'mpesa' => (float) 0,
                        'cash' => (float) 0,
                    ],
                    'total_revenue' => (float) 0,
                    'total_cogs' => (float) 0,
                    'total_gross_profit' => (float) 0,
                    'gross_profit_margin' => (float) 0,
                    'aov' => (float) 0,
                ]
            ]);
        }

        if ($user->role === UserRoles::CASHIER) {
            return inertia('app/dashboards/Cashier', [
                'user' => $user,
                'stats' => [
                    'today' => [
                        'orders_count'    => (int) 0,
                        'sales_total'     => (float) 0,
                        'cash_collected'  => (float) 0,
                        'mpesa_collected' => (float) 0,
                    ],
                    'needs_attention' => [
                        'pending_payment'  => (int) 0,
                        'ready_for_pickup' => (int) 0,
                    ],
                    'low_stock' => 0,
                ],
            ]);
        }

        if ($user->role === UserRoles::CUSTOMER) {
            // $ordersQuery = $user->orders();

            $stats = [
                // 'total_orders' => $ordersQuery->count(),
                // 'pending_orders' => (clone $ordersQuery)->pending()->count(),
                // 'processing_orders' => (clone $ordersQuery)->processing()->count(),
                // 'shipped_orders' => (clone $ordersQuery)->shipped()->count(),
                // 'delivered_orders' => (clone $ordersQuery)->delivered()->count(),
                // 'cancelled_orders' => (clone $ordersQuery)->cancelled()->count(),
                // 'active_orders' => (clone $ordersQuery)->active()->count(), // Using the new scope
                // 'total_spent' => (clone $ordersQuery)->paid()->sum('total_amount'),
                // 'recent_orders' => OrderResource::collection($ordersQuery->latest()->paginate(20)),
            ];

            return inertia('app/dashboards/Customer', [
                'user' => $user,
                'stats' => $stats
            ]);
        }
        return inertia('app/dashboards/Dashboard');
    }
}
