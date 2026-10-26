<?php

namespace Modules\Storefront\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// use Modules\Product\Models\ProductBrand;
use Modules\Product\Models\Product;
// use Modules\Product\Http\Resources\BrandResource;
use Modules\Product\Http\Resources\ProductHomePageResource;

class HomePageController extends Controller
{
    public function index()
    {
        // $brands = ProductBrand::orderBy('name')->where('is_active', true)->get();

        $new_arrivals = Product::query()
            ->sellable()
            ->where('is_new', true)
            ->where('is_active', true)
            ->with('images')
            ->limit(8)
            ->get();
        
        $most_popular = $this->getMostPopularProducts();

        return inertia('storefront/home/Index', [
            // 'brands' => BrandResource::collection($brands),
            'new_arrivals' => ProductHomePageResource::collection($new_arrivals)->resolve(),
            'most_popular' => ProductHomePageResource::collection($most_popular)->resolve()
        ]);
    }

    private function getMostPopularProducts()
    {
        // Get up to 4 featured products
        $featured = Product::query()
            ->sellable()
            ->where('is_featured', true)
            ->where('is_active', true)
            ->with('images')
            ->limit(12)
            ->get();

        // If we have 16 featured products, return them
        if ($featured->count() >= 12) {
            return $featured;
        }

        // If we have less than 4 featured, get the remaining from random products
        $needed = 12 - $featured->count();
        
        $random = Product::query()
            ->sellable()
            ->where('is_active', true)
            ->where('is_featured', false) // Exclude featured products
            ->whereNotIn('id', $featured->pluck('id')) // Exclude already selected
            ->with('images')
            ->inRandomOrder()
            ->limit($needed)
            ->get();

        // Merge featured and random products
        return $featured->merge($random);
    }
}
