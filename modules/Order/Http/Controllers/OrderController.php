<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Exception;
use Modules\Order\Enums\DeliveryStatusEnum;
use Modules\Order\Enums\OrderStatusEnum;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Payment\Models\Payment;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Http\Resources\OrderIndexPageResource;
use Modules\Order\Http\Resources\ProductPOSResource;
use Modules\Order\Http\Requests\OrderRequest;
use Modules\User\Enums\UserRoles;
use Modules\User\Models\User;
use Modules\Order\Actions\CreatePosOrderAction;

class OrderController extends Controller
{
    public function __construct(protected CreatePosOrderAction $createPosOrder) {}

    public function index(Request $request)
    {
        $orders = Order::query()
            ->search($request->query('search'))
            ->when($request->status, fn ($q, $v) => $q->where('order_status', $v))
            ->when($request->created_by, fn ($q, $v) => $q->where('created_by', $v))
            ->when($request->delivery_method, fn ($q, $v) => $q->where('delivery_method', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('sold_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('sold_at', '<=', $v))
            ->when($request->payment_status === 'paid', fn ($q) => $q->paid())
            ->when($request->payment_status === 'unpaid', fn ($q) => $q->where('amount_paid', 0))
            ->when($request->payment_status === 'partially_paid', fn ($q) => $q->partiallyPaid())
            ->with(['user', 'createdBy', 'updatedBy'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('app/orders/orders/Index', [
            'orders'  => OrderIndexPageResource::collection($orders),
            'filters' => $request->only([
                'search', 'status', 'created_by', 'delivery_method',
                'payment_status', 'from', 'to',
            ]),
            'statuses' => collect(OrderStatusEnum::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'cashiers' => User::query()->whereIn('role', [UserRoles::CASHIER->value, UserRoles::ADMIN->value, UserRoles::SUPER_ADMIN->value])->orderBy('name')->get(['id', 'name'])
        ]);
    }

    public function create()
    {
        $products = Product::query()
            ->orderBy('name')
            ->where('is_active', true)
            ->get();

        $recent_orders = Order::query()
            ->visibleTo(Auth::user())
            ->recent()
            ->with('user:id,name', 'orderItems')
            ->limit(10)
            ->get();

        return inertia('app/orders/orders/Create', [
            'products' => ProductPOSResource::collection($products),
            'recent_orders' => OrderResource::collection($recent_orders),
            'can_view_all' => in_array(Auth::user()->role, [
                UserRoles::SUPER_ADMIN,
                UserRoles::ADMIN,
            ], true),
        ]);
    }

    public function store(OrderRequest $request)
    {
        $validated = $request->validated();

        try {
            $this->createPosOrder->execute($validated, Auth::user());
        } catch (\Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);

            return back()->withInput();
        }

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Order added successfully',
        ]);

        return to_route('orders.create');
    }

    public function edit(Order $order)
    {
        $order->load('orderItems', 'payments', 'orderStatuses');

        return inertia('app/orders/orders/Edit', [
            'order' => new OrderResource($order),
            'orderStatuses' => OrderStatusEnum::labels(),
            'deliveryStatuses' => DeliveryStatusEnum::labels(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_status' => 'required|string|in:' . implode(',', OrderStatusEnum::values()),
            'delivery_status' => 'nullable|string|in:' . implode(',', DeliveryStatusEnum::values()),
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {
            // Update order status if provided
            if (isset($validated['order_status']) && $validated['order_status'] !== $order->order_status->value) {
                $status = OrderStatusEnum::from($validated['order_status']);
                $order->updateOrderStatus(
                    $status,
                    $validated['notes'] ?? null,
                    $validated['metadata'] ?? null,
                    Auth::id()
                );
            }

            // Update delivery status if provided
            if (isset($validated['delivery_status']) && $validated['delivery_status'] !== $order->delivery_status->value) {
                $status = DeliveryStatusEnum::from($validated['delivery_status']);
                $order->updateDeliveryStatus(
                    $status,
                    $validated['notes'] ?? null,
                    $validated['metadata'] ?? null,
                    Auth::id()
                );
            }

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Order updated successfully"
            ]);

            return to_route('orders.index');

        } catch (\Throwable $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Order failed to update: {$e->getMessage()}"
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Order $order)
    {
        try {
            $order->delete();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Order deleted successfully"
            ]);

            return to_route('orders.index');
        } catch (Exception $e) {
            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to delete order: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }
}