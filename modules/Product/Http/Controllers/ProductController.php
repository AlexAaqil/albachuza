<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Exception;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Http\Resources\ProductIndexPageResource;
use Modules\Product\Http\Requests\ProductRequest;
use Modules\Product\Services\ProductImageService;

class ProductController extends Controller
{
    public function __construct(protected ProductImageService $productImages) {}

    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['images', 'category', 'brand'])
            ->search($request->search);

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $products = $query->orderBy('name')->paginate(50);

        $duplicateIds = $this->getDuplicateProductIds();

        return inertia('app/products/products/Index', [
            'products' => ProductIndexPageResource::collection($products),
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
            'duplicate_product_ids' => $duplicateIds,
        ]);
    }

    protected function getDuplicateProductIds(): array
    {
        // Get all products with the fields we want to check for duplicates
        $products = Product::select('id', 'name', 'description', 'price')->get();
        
        // Group products by normalized name, description, and price
        $groups = [];
        $duplicateIds = [];
        
        foreach ($products as $product) {
            // Create a unique key based on normalized fields
            $key = $this->getDuplicateKey($product);
            
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            
            $groups[$key][] = $product->id;
        }
        
        // Find groups with more than one product (duplicates)
        foreach ($groups as $group) {
            if (count($group) > 1) {
                $duplicateIds = array_merge($duplicateIds, $group);
            }
        }
        
        return $duplicateIds;
    }

    protected function getDuplicateKey($product): string
    {
        // Normalize the fields for comparison
        $normalizedName = trim(strtolower($product->name));
        $normalizedDescription = trim(strtolower($product->description ?? ''));
        $normalizedPrice = number_format((float)$product->price, 2, '.', '');
        
        return md5($normalizedName . '|' . $normalizedDescription . '|' . $normalizedPrice);
    }

    public function create()
    {
        $product_categories = ProductCategory::select('id', 'name')->orderBy('name')->get();
        $product_brands = ProductBrand::select('id', 'name')->orderBy('name')->get();

        return inertia('app/products/products/Create', [
            'product_categories' => $product_categories,
            'product_brands' => $product_brands
        ]);
    }

    public function store(ProductRequest $request)
    {
        $validated_data = $request->validated();

        $images = $validated_data['images'] ?? [];
        unset($validated_data['images'], $validated_data['image_order']);

        try {
            DB::beginTransaction();

            $product = Product::create($validated_data);

            if (!empty($images)) {
                $this->productImages->storeUploads($product, $images);
            }

            DB::commit();
        
            Inertia::flash('toast', [
                'type' => "success",
                'message' => $product->tracksInventory() ? "Product created. Add initial stock to start tracking." : "Product created successfully.",
            ]);

            if ($product->tracksInventory()) {
                return to_route('products-inventory.create', $product);
            }

            return to_route('products.index');
        } catch (Exception $e) {
            DB::rollback();
            $this->productImages->discardWritten();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to save product: {$e->getMessage()}"
            ]);

            return back()->withInput();
        }
    }

    public function edit(Product $product)
    {
        $product_categories = ProductCategory::select('id', 'name')->orderBy('name')->get();
        $product_brands = ProductBrand::select('id', 'name')->orderBy('name')->get();

        $product->load('category', 'images');

        return inertia('app/products/products/Edit', [
            'product' => $product,
            'product_categories' => $product_categories,
            'product_brands' => $product_brands
        ]);
    }
    
    public function update(ProductRequest $request, Product $product)
    {
        $validated = $request->validated();

        $images = $validated['images'] ?? [];
        $imageOrder = $validated['image_order'] ?? [];
        unset($validated['images'], $validated['image_order']);

        $imagesToDelete = $request->input('images_to_delete', []);
        if (is_string($imagesToDelete)) {
            $imagesToDelete = json_decode($imagesToDelete, true) ?? [];
        }

        DB::beginTransaction();

        try {

            $was_tracking = $product->track_inventory;

            $product->update($validated);

            // Remove the images the user took out (their files go once this is committed)
            if (!empty($imagesToDelete)) {
                $this->productImages->deleteForProduct($product, $imagesToDelete);
            }

            // Add new uploads after the remaining ones, then apply the order from the form
            $created = !empty($images)
                ? $this->productImages->storeUploads($product, $images)
                : [];

            if (!empty($imageOrder)) {
                $this->productImages->applyOrder($product, $imageOrder, $created);
            }

            DB::commit();

            $needs_initial_stock = $product->track_inventory && $product->current_stock == 0 && $product->inventoryMovements()->doesntExist();

            if ($needs_initial_stock) {
                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => $was_tracking ? 'Product updated. Set your initial stock to start tracking.' : 'Inventory tracking enabled. Set your initial stock level.',
                ]);

                return to_route('products-inventory.create', $product);
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Product updated successfully',
            ]);

            return to_route('products.index');
        } catch (ValidationException $e) {
            DB::rollBack();
            $this->productImages->discardWritten();

            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->productImages->discardWritten();

            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Product failed to update: {$e->getMessage()}"
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function toggleAttribute(Request $request, Product $product)
    {
        $request->validate([
            'attribute' => 'required|in:is_featured,is_new,is_active,track_inventory',
            'value' => 'required|boolean'
        ]);

        try {
            $attribute = $request->attribute;
            $value = $request->value;
            
            $product->update([$attribute => $value]);

            return response()->json([
                'success' => true,
                'message' => "Product {$attribute} updated successfully",
                'product' => [
                    'id' => $product->id,
                    $attribute => $product->$attribute,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy(Product $product)
    {
        $product->delete();

        Inertia::flash('toast', [
            'type' => "success",
            'message' => "Product deleted successfully"
        ]);
        
        return to_route('products.index');
    }
}