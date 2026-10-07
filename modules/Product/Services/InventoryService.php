<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Enums\InventoryMovementTypes;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Exceptions\StockTrackingDisabledException;

class InventoryService
{
    /**
     * Get current stock for a product.
     */
    public function getCurrentStock(Product $product): int
    {
        if (!$product->track_inventory) {
            return PHP_INT_MAX; // Effectively unlimited
        }
        
        return (float) $product->current_stock;
    }
    
    /**
     * Check if product has sufficient stock.
     */
    public function hasSufficientStock(Product $product, float $quantity): bool
    {
        if (!$product->track_inventory) {
            return true;
        }
        
        return (float) $product->current_stock >= $quantity;
    }
    
    /**
     * Add stock to a product.
     */
    public function addStock(
        Product $product,
        float $quantity,
        InventoryMovementTypes $type,
        string $source,
        ?string $notes = null,
        ?array $metadata = null,
    ): InventoryMovement {
        if (!$product->tracksInventory()) {
            throw StockTrackingDisabledException::forProduct($product);
        }

        $movement = $product->addStock(
            quantity: $quantity,
            type: $type,
            source: $source,
            notes: $notes,
            metadata: $metadata,
        );

        Log::info("Stock added to product", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'type' => $type->label(),
            'new_stock' => $product->current_stock,
            'source' => $source
        ]);

        return $movement;
    }
    
    /**
     * Remove stock from a product.
     */
    public function removeStock(
        Product $product,
        float $quantity,
        InventoryMovementTypes $type, // Changed from string to enum
        string $source,
        ?string $notes = null,
        ?array $metadata = null
    ): InventoryMovement {
        if (!$product->tracksInventory()) {
            throw StockTrackingDisabledException::forProduct($product);
        }

        if (!$this->hasSufficientStock($product, $quantity)) {
            throw new InsufficientStockException(
                "Only {$product->current_stock} units available for '{$product->name}'"
            );
        }
        
        $movement = $product->removeStock(
            quantity: $quantity,
            type: $type,
            notes: $notes,
            metadata: $metadata,
            source: $source
        );

        Log::info("Stock removed from product", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'type' => $type->label(),
            'new_stock' => $product->current_stock,
        ]);

        return $movement;
    }
    
    /**
     * Deduct stock for an order (at payment confirmation).
     */
    public function deductForOrder(Product $product, int $quantity, int $orderId): InventoryMovement
    {
        return $this->removeStock(
            product: $product,
            quantity: $quantity,
            source: 'order',
            type: InventoryMovementTypes::SALE, // Use enum
            notes: "Order #{$orderId} - Payment confirmed",
            metadata: ['order_id' => $orderId]
        );
    }
    
    /**
     * Add stock for a customer return.
     */
    public function addForReturn(Product $product, int $quantity, int $orderId): InventoryMovement
    {
        return $this->addStock(
            product: $product,
            quantity: $quantity,
            source: 'order',
            type: InventoryMovementTypes::RETURN, // Use enum
            notes: "Return from order #{$orderId}",
            metadata: ['order_id' => $orderId]
        );
    }
    
    /**
     * Get inventory movement history for a product.
     */
    public function getMovementHistory(Product $product, int $perPage = 20)
    {
        return $product->inventoryMovements()
            ->with('createdBy', 'updatedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
    
    /**
     * Get low stock products for a shop.
     */
    public function getLowStockProducts()
    {
        return Product::query()
            ->where('track_inventory', true)
            ->where('current_stock', '<=', DB::raw('low_stock_threshold'))
            ->where('current_stock', '>', 0)
            ->orderBy('current_stock', 'asc')
            ->get();
    }
    
    /**
     * Get out of stock products for a shop.
     */
    public function getOutOfStockProducts()
    {
        return Product::query()
            ->where('track_inventory', true)
            ->where('current_stock', '<=', 0)
            ->orderBy('name', 'asc')
            ->get();
    }
    
    /**
     * Bulk update stock for multiple products.
     */
    public function bulkUpdateStock(array $updates): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($updates as $update) {
            try {
                $product = Product::findOrFail($update['product_id']);

                if (!$product->tracksInventory()) {
                    $results['failed'][] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'error' => 'Stock tracking is disabled for this product.',
                    ];
                    continue;
                }
                
                if ($update['type'] === 'add') {
                    $this->addStock(
                        product: $product,
                        quantity: $update['quantity'],
                        source: 'manual',
                        type: InventoryMovementTypes::RESTOCK, // Use enum
                        notes: $update['notes'] ?? null
                    );
                } else {
                    $this->removeStock(
                        product: $product,
                        quantity: $update['quantity'],
                        source: 'manual',
                        type: InventoryMovementTypes::ADJUSTMENT, // Use enum
                        notes: $update['notes'] ?? null
                    );
                }
                
                $results['success'][] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'new_stock' => $product->current_stock
                ];
                
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'product_id' => $update['product_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}