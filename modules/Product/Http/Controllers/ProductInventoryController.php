<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;
use Modules\Product\Models\Product;
use Modules\Product\Enums\InventoryMovementTypes;
use Modules\Product\Http\Requests\InventoryMovementRequest;
use Modules\Product\Http\Resources\InventoryMovementResource;
use Modules\Product\Services\InventoryService;
use Modules\Product\Http\Resources\ProductsPageResource as ResourcesProductsPageResource;
use Modules\Product\Exceptions\StockTrackingDisabledException;
use Modules\Product\Exceptions\InsufficientStockException;

class ProductInventoryController extends Controller
{
    public function __construct(protected InventoryService $inventoryService){}

    public function index(Request $request)
    {
        $query = Product::query();
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->where('current_stock', '>', 0)
                          ->where('track_inventory', true);
                    break;
                case 'low_stock':
                    $query->where('track_inventory', true)
                          ->where('current_stock', '>', 0)
                          ->whereColumn('current_stock', '<=', 'low_stock_threshold');
                    break;
                case 'out_of_stock':
                    $query->where('track_inventory', true)
                          ->where('current_stock', '<=', 0);
                    break;
                case 'unlimited':
                    $query->where('track_inventory', false);
                    break;
            }
        }
        
        $products = $query->orderBy('name')->paginate(20);
        
        // Get summary stats
        $stats = [
            'total_products' => Product::count(),
            'low_stock_count' => $this->inventoryService->getLowStockProducts()->count(),
            'out_of_stock_count' => $this->inventoryService->getOutOfStockProducts()->count(),
            'total_value' => Product::query()
                ->where('track_inventory', true)
                ->sum(DB::raw('current_stock * cost_price'))
        ];

        return inertia('app/products/inventory/Index', [
            'products' => ResourcesProductsPageResource::collection($products),
            'stats' => $stats,
            'filters' => $request->only(['search', 'stock_status'])
        ]);
    }

    public function create(Product $product)
    {
        if (!$product->tracksInventory()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "Stock tracking is disabled for '{$product->name}'. Enable it first.",
            ]);

            return to_route('products-inventory.index');
        }

        return inertia('app/products/inventory/Create', [
            'product' => $product,
            'current_stock' => $product->current_stock,
            'low_stock_threshold' => $product->low_stock_threshold,
            'track_inventory' => $product->track_inventory,
            'movement_types' => [
                'add' => InventoryMovementTypes::addOperations(),
            ]
        ]);
    }

    public function store(InventoryMovementRequest $request, Product $product)
    {
        $validated = $request->validated();

        try {
            // Convert the integer type from the form to enum
            $movement_type = InventoryMovementTypes::from($validated['type']);
                    
            $movement = $this->inventoryService->addStock(
                product: $product,
                quantity: $request->quantity,
                type: $movement_type,
                source: 'manual',
                notes: $validated['notes'] ?? null,
            );

            Inertia::flash('toast', [
                'type' => "success",
                'message' => "Inventory updated successfully"
            ]);

            return to_route('products-inventory.index');
        } catch (StockTrackingDisabledException $e) {
            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to update inventory: {$e->getMessage()}"
            ]);

            return back();
        } catch (InsufficientStockException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
            return back();
        } catch (Exception $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Failed to update inventory: {$e->getMessage()}",
            ]);
            return back();
        }
    }

    public function edit(Product $product)
    {
        if (!$product->tracksInventory()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "Stock tracking is disabled for '{$product->name}'. Enable it first.",
            ]);

            return to_route('products-inventory.index');
        }
        
        return inertia('app/products/inventory/Edit', [
            'product' => $product,
            'current_stock' => $product->current_stock,
            'low_stock_threshold' => $product->low_stock_threshold,
            'track_inventory' => $product->track_inventory,
            'movement_types' => [
                'remove' => InventoryMovementTypes::removeOperations(),
            ]
        ]);
    }

    public function update(InventoryMovementRequest $request, Product $product)
    {
        $validated = $request->validated();

        try {
            // Convert the integer type from form to enum
            $movement_type = InventoryMovementTypes::from($validated['type']);
            
            $this->inventoryService->removeStock(
                product: $product,
                quantity: (float) $validated['quantity'],
                type: $movement_type,
                source: 'manual',
                notes: $validated['notes'] ?? null,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Stock removed successfully',
            ]);

            return to_route('products-inventory.index');
        } catch (StockTrackingDisabledException $e) {
            Inertia::flash('toast', [
                'type' => "error",
                'message' => "Failed to update inventory: {$e->getMessage()}"
            ]);

            return back();
        } catch (InsufficientStockException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
            return back();
        } catch (Exception $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Failed to update inventory: {$e->getMessage()}",
            ]);
            return back();
        }
    }

    public function history(Product $product)
    {
        $movements = $this->inventoryService->getMovementHistory($product, 50);
        
        return inertia('app/products/inventory/History', [
            'product' => $product,
            'movements' => InventoryMovementResource::collection($movements)
        ]);
    }
}
