<?php

namespace Modules\Order\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Order\Enums\OrderStatusEnum;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Modules\Product\Services\InventoryService;
use Modules\User\Models\User;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    /**
     * Create an order from a normalized payload.
     *
     * The caller (a channel-specific action) is responsible for producing
     * the shape defined below. This method is intentionally channel-blind:
     * it makes no decisions based on 'pos' vs 'ecommerce' vs anything else.
     *
     * @param array{
     *   order_channel: string,
     *   customer: array{name: ?string, phone: ?string, email: ?string},
     *   delivery: array{method: string, cost: float, location: string, area: string, address: string, status: string},
     *   cart_items: array<int, array{id: int, price: float, quantity: int}>,
     *   payments: array<int, array{method: string, amount: float}>,
     *   user_id: ?int,
     *   initial_order_status: string,
     *   deduct_inventory: bool,
     * } $data
     */
    public function create(array $data, ?User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor) {
            // --- CALCULATE TOTALS ---
            $subtotal = collect($data['cart_items'])
                ->sum(fn ($item) => $item['price'] * ($item['quantity'] ?? 1));

            $total_cost_price = 0;
            foreach ($data['cart_items'] as $item) {
                $product = Product::find($item['id']);
                $total_cost_price += ($product->cost_price ?? 0) * ($item['quantity'] ?? 1);
            }

            $total_selling_price = $subtotal + $data['delivery']['cost'];
            $total_paid = collect($data['payments'])->sum('amount');

            // --- CREATE THE ORDER ---
            $order = Order::create([
                'order_number' => 'Ord_' . strtoupper(Str::random(6)) . '_' . now()->format('ymd'),
                'order_channel' => $data['order_channel'],
                'order_status' => $data['initial_order_status'],

                'subtotal' => $subtotal,
                'shipping_cost' => $data['delivery']['cost'],
                'total_selling_price' => $total_selling_price,
                'total_cost_price' => $total_cost_price,
                'amount_paid' => $total_paid,

                'customer_name' => $data['customer']['name'],
                'customer_phone' => $data['customer']['phone'],
                'customer_email' => $data['customer']['email'],

                'delivery_method' => $data['delivery']['method'],
                'delivery_location' => $data['delivery']['location'],
                'delivery_area' => $data['delivery']['area'],
                'delivery_address' => $data['delivery']['address'],
                'delivery_status' => $data['delivery']['status'],

                'sold_at' => now(),

                'user_id' => $data['user_id'],
            ]);

            // --- INITIAL ORDER STATUS RECORD ---
            $order->orderStatuses()->create([
                'type' => 'order',
                'status' => $data['initial_order_status'],
                'notes' => 'Order created via ' . $data['order_channel'],
                'user_id' => $actor?->id,
                'is_system' => $actor === null,
                'changed_at' => now(),
            ]);

            // --- INITIAL DELIVERY STATUS RECORD ---
            $order->orderStatuses()->create([
                'type' => 'delivery',
                'status' => $data['delivery']['status'],
                'notes' => 'Initial delivery status',
                'user_id' => $actor?->id,
                'is_system' => $actor === null,
                'changed_at' => now(),
            ]);

            // --- CREATE ORDER ITEMS ---
            foreach ($data['cart_items'] as $item) {
                $product = Product::find($item['id']);
                $quantity = $item['quantity'] ?? 1;

                if ($data['deduct_inventory'] && $product->tracksInventory()) {
                    $this->inventoryService->deductForOrder(
                        product: $product,
                        quantity: $quantity,
                        orderId: $order->id,
                    );
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku ?? null,
                    'quantity' => $quantity,
                    'cost_price' => $product->cost_price ?? 0,
                    'selling_price' => $item['price'],
                    'subtotal' => $item['price'] * $quantity,
                    'total' => $item['price'] * $quantity,
                ]);
            }

            // --- CREATE PAYMENT RECORDS ---
            foreach ($data['payments'] as $paymentData) {
                if (($paymentData['amount'] ?? 0) > 0) {
                    Payment::create([
                        'order_id' => $order->id,
                        'payment_method' => $paymentData['method'],
                        'transaction_reference' => null,
                        'amount' => $paymentData['amount'],
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }
            }

            // --- FINAL STATUS BASED ON PAYMENT ---
            $fullyPaid = $total_paid >= $total_selling_price;

            if ($fullyPaid && $data['delivery']['method'] === 'delivery') {
                $order->updateOrderStatus(
                    OrderStatusEnum::CONFIRMED,
                    'Order fully paid, confirmed',
                    null,
                    $actor?->id,
                );
            }

            if ($fullyPaid && $data['delivery']['method'] === 'shop') {
                $order->updateOrderStatus(
                    OrderStatusEnum::COMPLETED,
                    'Order fully paid, and picked up',
                    null,
                    $actor?->id,
                );
            }

            return $order;
        });
    }
}