<?php

namespace Modules\Order\Actions;

use Modules\Order\Enums\DeliveryStatusEnum;
use Modules\Order\Enums\OrderStatusEnum;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderService;
use Modules\User\Models\User;
use Modules\Product\Models\Product;

class CreateEcommerceOrderAction
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * Create an order originating from the Ecommerce (online) channel.
     *
     * The actor is the authenticated customer, or null for guest checkout.
     * When null, the order's user_id is null and audit rows are marked
     * as is_system = true.
     *
     * @param array $validated Output of EcommerceOrderRequest::validated()
     * @param User|null $actor The authenticated customer, or null for guest checkout
     */
    public function execute(array $validated, ?User $actor): Order
    {
        $cartItems = collect($validated['cart_items'])
            ->map(function (array $item) {
                $product = Product::find($item['id']);
                if (! $product) {
                    throw new \RuntimeException("Product {$item['id']} no longer available.");
                }

                return [
                    'id'       => $product->id,
                    'price'    => (float) $product->selling_price,
                    'quantity' => $item['quantity'],
                ];
            })
            ->all();
        
        $data = [
            'order_channel' => 'ecommerce',

            'customer' => [
                'name'  => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'],
            ],

            'delivery' => [
                'method'   => 'delivery',
                'cost'     => (float) $validated['shipping_cost'],
                'location' => $validated['shipping_location'],
                'area'     => $validated['shipping_area'],
                'address'  => $validated['shipping_address'],
                'status'   => DeliveryStatusEnum::PENDING->value,
            ],

            'cart_items' => $cartItems,
            'payments'   => $validated['payments'] ?? [],

            'user_id' => $actor?->id,

            'initial_order_status' => OrderStatusEnum::PENDING->value,

            'deduct_inventory' => false,
        ];

        return $this->orderService->create($data, $actor);
    }
}