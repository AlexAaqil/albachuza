<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Image;
use Inertia\Inertia;
use Exception;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Http\Requests\ProductCategoryRequest;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductCategory::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('name')->withCount('products')->get();

        return inertia('app/products/categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $request->search
            ]
        ]);
    }

    public function create()
    {
        return inertia('app/products/categories/Create');
    }

    public function store(ProductCategoryRequest $request)
    {
        $validated_data = $request->validated();

        $image = $validated_data['image'] ?? null;
        unset($validated_data['image']);

        try {
            DB::beginTransaction();

            $category = ProductCategory::create($validated_data);

            if ($image && $image instanceof \Illuminate\Http\UploadedFile) {
                $filename = $this->uploadImage($image, $category);
                $category->update(['image' => $filename]);
            }

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Product Category created successfully"
            ]);

            return to_route('product-categories.index');
        } catch (Exception $e) {
            DB::rollback();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to save category: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }

    public function edit(ProductCategory $product_category)
    {
        return inertia('app/products/categories/Edit', [
            'product_category' => $product_category
        ]);
    }

    public function update(ProductCategoryRequest $request, ProductCategory $product_category)
    {
        $validated_data = $request->validated();

        $image = $validated_data['image'] ?? null;
        unset($validated_data['image']);

        try {
            DB::beginTransaction();

            $product_category->update($validated_data);

            if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
                if ($product_category->image) {
                    Storage::disk('public')->delete("product-categories/{$product_category->image}");
                    $product_category->update(['image' => null]);
                }
            }

            if ($request->hasFile('image')) {
                // Delete old logo if exists
                if ($product_category->image) {
                    $oldPath = "product-categories/{$product_category->image}";
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $image_path = $this->uploadImage($request->file('image'), $product_category);
                $product_category->update(['image' => $image_path]);
            }

            DB::commit();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Category: {$request->name} updated successfully"
            ]);

            return to_route('product-categories.index');
        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to update category: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }

    public function destroy(ProductCategory $product_category)
    {
        try {
            $product_category->delete();

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Category deleted successfully"
            ]);

            return to_route('product-categories.index');
        } catch (Exception $e) {
            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to delete category: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }

    private function uploadImage($file, ProductCategory $product_category): string
    {
        $slug = Str::slug($product_category->name);
        $timestamp = now()->format('Ymd');
        $random = 'albachuza_'.Str::random(6);
        $filename = "{$slug}_{$timestamp}_{$random}.webp";

        Image::fromUpload($file)
            ->cover(200, 200)
            ->toWebp()
            ->storeAs('product-categories', $filename, 'public');

        return $filename;
    }
}