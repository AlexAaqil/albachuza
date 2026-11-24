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
    private const NEW_ARRIVALS_LIMIT = 8;
    private const MOST_POPULAR_LIMIT = 20;

    public function index()
    {
        // $brands = ProductBrand::orderBy('name')->where('is_active', true)->get();

        $new_arrivals = Product::query()
            ->newArrivals()
            ->with('images')
            ->limit(self::NEW_ARRIVALS_LIMIT)
            ->get();
        
        $most_popular = $this->getMostPopularProducts(self::MOST_POPULAR_LIMIT);

        return inertia('storefront/home/Index', [
            // 'brands' => BrandResource::collection($brands),
            'new_arrivals' => ProductHomePageResource::collection($new_arrivals)->resolve(),
            'most_popular' => ProductHomePageResource::collection($most_popular)->resolve()
        ]);
    }

    private function getMostPopularProducts(int $limit)
    {
        $featured = Product::query()
            ->featured()
            ->with('images')
            ->limit($limit)
            ->get();

        if ($featured->count() >= $limit) {
            return $featured;
        }

        $needed = $limit - $featured->count();
        
        $random = Product::query()
            ->sellable()
            ->where('is_active', true)
            ->where('is_featured', false)
            ->whereNotIn('id', $featured->pluck('id'))
            ->with('images')
            ->inRandomOrder()
            ->limit($needed)
            ->get();

        // Merge featured and random products
        return $featured->merge($random);
    }
}
