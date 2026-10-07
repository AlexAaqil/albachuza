<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Http\Requests\ProductBrandRequest;

class ProductBrandController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductBrand::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        }

        $brands = $query->orderBy('name')->withCount('products')->get();

        return inertia('app/products/brands/Index', [
            'brands' => $brands,
            'filters' => [
                'search' => $request->search
            ]
        ]);
    }

    public function create()
    {
        return inertia('app/products/brands/Create');
    }

    public function store(ProductBrandRequest $request)
    {
        try {
            DB::beginTransaction();

            ProductBrand::create([
                'name' => $request->name,
            ]);

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Product Brand created successfully"
            ]);

            return to_route('product-brands.index');
        } catch (Exception $e) {
            DB::rollback();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to save brand: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }

    public function edit(ProductBrand $product_brand)
    {
        return inertia('app/products/brands/Edit', [
            'product_category' => $product_brand
        ]);
    }

    public function update(ProductBrand $product_brand, ProductBrandRequest $request)
    {
        try {
            DB::beginTransaction();

            $product_brand->update([
                'name' => $request->name,
            ]);

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Brand: {$request->name} updated successfully"
            ]);

            return to_route('product-brands.index');
        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to update brand: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }

    public function destroy(ProductBrand $product_brand)
    {
        try {
            $product_brand->delete();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Brand deleted successfully"
            ]);

            return to_route('product-brands.index');
        } catch (Exception $e) {
            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to delete brand: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }
}