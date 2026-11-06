<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total   = (float) $this->total_selling_price;
        $paid    = (float) $this->amount_paid;
        $balance = max(0, $total - $paid);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'order_channel' => $this->order_channel,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'delivery_method' => $this->delivery_method,
            'delivery_location' => $this->delivery_location,
            'delivery_area' => $this->delivery_area,
            'delivery_address' => $this->delivery_address,
            'delivery_cost' => $this->shipping_cost,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'discount_notes' => $this->discount_notes,

            'total_selling_price' => $this->total_selling_price,
            'amount_paid'    => $this->total_paid,   // show live value, not the cache
            'total_paid'     => $this->total_paid,
            'balance'        => $this->balance,
            'is_fully_paid'   => $this->isFullyPaid(),
            'balance_label'  => $this->isFullyPaid()
                ? 'Paid in full'
                : 'Balance: ' . number_format($this->balance, 2),
            'payment_status' => $this->payment_status,
            
            'order_status' => $this->order_status,
            'order_status_label' => $this->order_status ? $this->order_status->label() : null,
            'delivery_status' => $this->delivery_status,
            'delivery_status_label' => $this->delivery_status ? $this->delivery_status->label() : null,
            'notes' => $this->notes,
            'sold_at' => $this->sold_at?->setTimezone('Africa/Nairobi')->format('d-m-Y H:i'),
            'payments' => $this->payments,
            'order_items' => $this->orderItems,
            'order_statuses' => $this->orderStatuses,
            'can_be_cancelled' => $this->canBeCancelled(),

            'admin_notes' => $this->admin_notes,
            'cashier_notes' => $this->cashier_notes,

            'user'       => $this->whenLoaded('user',       fn () => $this->user?->only('id', 'name')),
            'created_by' => $this->whenLoaded('createdBy',  fn () => $this->createdBy?->only('id', 'name')),
            'updated_by' => $this->whenLoaded('updatedBy',  fn () => $this->updatedBy?->only('id', 'name')),
        ];
    }
}