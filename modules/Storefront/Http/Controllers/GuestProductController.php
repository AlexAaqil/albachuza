<?php

namespace Modules\Storefront\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Http\Resources\ProductDetailsResource;
use Modules\Product\Http\Resources\ProductIndexPageResource;

class GuestProductController extends Controller
{
    public function productDetails(Product $product)
    {
        $product->load('images');

        $related_products = collect();

        // Same category
        if ($product->product_category_id) {
            $category_products = Product::query()
                ->where('product_category_id', $product->product_category_id)
                ->where('id', '!=', $product->id)
                ->sellable()
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit(8)
                ->get();
            
            $related_products = $related_products->concat($category_products);
        }

        // Same brand
        if ($related_products->count() < 8 && $product->brand_id) {
            $brandProducts = Product::query()
                ->where('brand_id', $product->brand_id)
                ->where('id', '!=', $product->id)
                ->sellable()
                ->where('is_active', true)
                // Exclude products we already grabbed from Step 1
                ->whereNotIn('id', $related_products->pluck('id'))
                ->inRandomOrder()
                ->limit(8 - $related_products->count()) 
                ->get();
            
            $related_products = $related_products->concat($brandProducts);
        }

        // Random products
        if ($related_products->count() < 8) {
            $randomProducts = Product::query()
                ->sellable()
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                // Exclude products we already grabbed from Steps 1 & 2
                ->whereNotIn('id', $related_products->pluck('id'))
                ->inRandomOrder()
                ->limit(8 - $related_products->count())
                ->get();
            
            $related_products = $related_products->concat($randomProducts);
        }

        return inertia('storefront/products/details/Index', [
            'product' => new ProductDetailsResource($product),
            'related_products' => ProductIndexPageResource::collection($related_products)
        ]);
    }

    public function shop(Request $request) 
    {
        $products = Product::query()
            ->search($request->search)
            ->when($request->category, function ($query, $category) {
                // Filter by category slug
                return $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            })
            ->sellable()
            ->where('is_active', true)
            ->where('current_stock', '>', 0)
            ->with('images')
            ->orderBy('name')
            ->paginate(30);
        
        $product_categories = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->select('id', 'name', 'slug')
            ->get();

        return inertia('storefront/products/shop/Index', [
            'products' => ProductIndexPageResource::collection($products),
            'product_categories' => $product_categories,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
            ]
        ]);
    }
}