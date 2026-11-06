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
            ->with('images')
            ->sellable()
            ->where('is_active', true)
            ->orderBy('name')
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

        if ($validated['order_status'] === 'completed' && ! $order->isFullyPaid()) {
            return back()->withErrors([
                'order_status' => 'Cannot complete an order that is not fully paid.',
            ]);
        }

        DB::beginTransaction();

        try {
            if ($validated['order_status'] !== $order->order_status->value) {
                $order->updateOrderStatus(
                    OrderStatusEnum::from($validated['order_status']),
                    $validated['notes'] ?? null,
                    $validated['metadata'] ?? null,
                    Auth::id()
                );
            }

            if (isset($validated['delivery_status']) && $validated['delivery_status'] !== $order->delivery_status?->value) {
                $order->updateDeliveryStatus(
                    DeliveryStatusEnum::from($validated['delivery_status']),
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

    public function recordPayment(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'transaction_reference'      => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        // Uses the live value from payments, not the cached amount_paid column
        if ($order->isFullyPaid()) {
            return back()->withErrors(['amount' => 'This order is already fully paid.']);
        }

        $balance = $order->balance;              // total_selling_price - total_paid
        $amount  = min((float) $validated['amount'], $balance);

        DB::transaction(function () use ($order, $amount, $validated) {
            $order->payments()->create([
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'completed',
                'amount' => $amount,
                'transaction_reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => $validated['paid_at'] ?? now(),
                // 'payment_metadata'      => array_filter([
                //     'notes' => $validated['notes'] ?? null,
                // ]),
            ]);

            // Refresh the denormalized cache from the source of truth
            $order->updateAmountPaid();
        });

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Payment recorded.',
        ]);

        return back();
    }

    public function applyDiscount(Request $request, Order $order)
    {
        $validated = $request->validate([
            'discount' => ['required', 'numeric', 'min:0'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        $totalPaid = $order->total_paid; // live from payments
        $items     = (float) $order->subtotal;
        $ship      = (float) $order->shipping_cost;
        $balance   = max(0, (float) $order->total_selling_price - $totalPaid);

        $discount = min((float) $validated['discount'], $items + $ship);

        // Can't discount more than what's still owed — that would mean
        // the customer has already paid more than the (new) total.
        if ($discount > $balance) {
            return back()->withErrors([
                'discount' => "Discount cannot exceed the outstanding balance of " . number_format($balance, 2) . ". Refund first.",
            ]);
        }

        $newTotal = $items + $ship - $discount;

        $order->update([
            'discount'                  => $discount,
            'total_selling_price'       => $newTotal,
            'discount_notes' => $validated['reason'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Discount applied.',
        ]);

        return back();
    }
}