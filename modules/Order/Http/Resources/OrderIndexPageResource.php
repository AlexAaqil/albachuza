<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderIndexPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'order_channel' => $this->order_channel,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'delivery_address' => $this->delivery_address,
            'total_selling_price' => $this->total_selling_price,
            'amount_paid' => $this->amount_paid,
            'payment_status' => $this->payment_status,
            'order_status' => $this->order_status,
            'order_status_label' => $this->order_status ? $this->order_status->label() : null,
            'delivery_status' => $this->delivery_status,
            'delivery_status_label' => $this->delivery_status ? $this->delivery_status->label() : null,
            'sold_at' => $this->sold_at?->setTimezone('Africa/Nairobi')->format('d-m-Y H:i'),
            
            'user'       => $this->whenLoaded('user',       fn () => $this->user?->only('id', 'name')),
            'created_by' => $this->whenLoaded('createdBy',  fn () => $this->createdBy?->only('id', 'name')),
            'updated_by' => $this->whenLoaded('updatedBy',  fn () => $this->updatedBy?->only('id', 'name')),
        ];
    }
}