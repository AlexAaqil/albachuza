<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailsResource extends JsonResource
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
            'price' => $this->price,
            'size' => $this->size,
            'color' => $this->color,
            'category_name' => $this->category_name,
            'brand_nane' => $this->brand_name,
            'is_new' => (bool) $this->is_new,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
            'images' => $this->whenLoaded('images', function() {
                return $this->images->map(function($image) {
                    return [
                        'url' => asset('storage/products/' . $image->name),
                        'alt' => $this->slug,
                    ];
                })->values()->toArray();
            }, []),
        ];
    }
}
