<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Http\Requests\ProductBrandRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Image;

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
        $validated_data = $request->validated();

        $image = $validated_data['image'] ?? null;
        unset($validated_data['image']);

        try {
            DB::beginTransaction();

            $brand = ProductBrand::create($validated_data);

            if ($image && $image instanceof \Illuminate\Http\UploadedFile) {
                $filename = $this->uploadImage($image, $brand);
                $brand->update(['image' => $filename]);
            }

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Brand created successfully"
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
            'product_brand' => $product_brand
        ]);
    }

    public function update(ProductBrandRequest $request, ProductBrand $product_brand)
    {
        $validated_data = $request->validated();

        $image = $validated_data['image'] ?? null;
        unset($validated_data['image']);

        try {
            DB::beginTransaction();

            $product_brand->update($validated_data);

            if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
                if ($product_brand->image) {
                    Storage::disk('public')->delete("brands/{$product_brand->image}");
                    $product_brand->update(['image' => null]);
                }
            }

            if ($request->hasFile('image')) {
                // Delete old logo if exists
                if ($product_brand->image) {
                    $oldPath = "brands/{$product_brand->image}";
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $image_path = $this->uploadImage($request->file('image'), $product_brand);
                $product_brand->update(['image' => $image_path]);
            }

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Brand updated successfully"
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

    private function uploadImage($file, ProductBrand $brand): string
    {
        $slug = Str::slug($brand->name);
        $timestamp = now()->format('Ymd');
        $random = 'albachuza_'.Str::random(6);
        $filename = "{$slug}_{$timestamp}_{$random}.png";

        Image::fromUpload($file)
            ->contain(200, 200, '#ffffff')
            ->toPng(90)
            ->storeAs('brands', $filename, 'public');

        return $filename;
    }
}