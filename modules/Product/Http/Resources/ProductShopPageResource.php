<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductShopPageResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,

            'price' => (float) $this->price,

            'current_stock' => $this->current_stock,
            'low_stock_threshold' => $this->low_stock_threshold,

            'category_name' => $this->category_name,
            'brand_name' => $this->brand_name,

            'is_new' => (bool) $this->is_new,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,

            'card_image_url' => $this->card_image_url,
        ];
    }
}
