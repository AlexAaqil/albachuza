<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'formatted_quantity' => $this->quantity > 0 ? "+{$this->quantity}" : "{$this->quantity}",
            'quantity_before' => $this->quantity_before,
            'quantity_after' => $this->quantity_after,
            'notes' => $this->notes,
            'created_at_formatted' => $this->created_at->timezone('Africa/Nairobi')->format('d-m-y H:i:s'),

            'source' => $this->source,
            'is_system' => $this->is_system,
            'performed_by' => $this->performed_by_name,

            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
        ];
    }
}
