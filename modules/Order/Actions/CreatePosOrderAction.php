<?php

namespace Modules\Order\Actions;

use Modules\Order\Enums\DeliveryStatusEnum;
use Modules\Order\Enums\OrderStatusEnum;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderService;
use Modules\User\Models\User;

class CreatePosOrderAction
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * Create an order originating from the POS channel.
     */
    public function execute(array $validated, User $cashier): Order
    {
        // Compute whether this order is paid in full at creation time.
        // The service will compute this too, but the action needs it
        $subtotal = collect($validated['cart_items'])->sum(fn ($item) => $item['price'] * ($item['quantity'] ?? 1));

        $totalSellingPrice = $subtotal + (float) $validated['delivery_cost'];
        $totalPaid = collect($validated['payments'])->sum('amount');
        $isFullyPaid = $totalPaid >= $totalSellingPrice;

        // Delivery status reflects reality at creation:
        //  - shop pickup + fully paid → goods handed over → PICKED_UP
        //  - everything else          → goods not yet handed over → PENDING
        $initialDeliveryStatus = (
            $validated['delivery_method'] === 'shop' && $isFullyPaid
        )
            ? DeliveryStatusEnum::PICKED_UP->value
            : DeliveryStatusEnum::PENDING->value;

        $data = [
            'order_channel' => $validated['order_channel'],

            'customer' => [
                'name'  => $validated['customer_name'] ?: 'Walk-in',
                'phone' => $validated['customer_phone'] ?: null,
                'email' => $validated['customer_email'] ?: null,
            ],

            'delivery' => [
                'method'   => $validated['delivery_method'],
                'cost'     => (float) $validated['delivery_cost'],
                'location' => 'shop',
                'area'     => 'shop',
                'address'  => 'shop',
                'status'   => $initialDeliveryStatus,
            ],

            'cart_items' => $validated['cart_items'],
            'payments'   => $validated['payments'],

            'user_id' => $validated['user_id'] ?? null,

            'initial_order_status' => $validated['delivery_method'] === 'delivery'
                ? OrderStatusEnum::PENDING->value
                : OrderStatusEnum::READY_FOR_PICKUP->value,

            'deduct_inventory' => true,
        ];

        return $this->orderService->create($data, $cashier);
    }
}