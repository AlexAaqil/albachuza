<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Product\Concerns\HasSku;
use Modules\Product\Enums\InventoryMovementTypes;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Exceptions\StockTrackingDisabledException;
use Modules\Support\Concerns\HasSlug;
use Modules\Support\Concerns\HasUuid;

class Product extends Model
{
    use HasSku, HasSlug, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'track_inventory' => 'boolean',
        'current_stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    protected $appends = [
        'brand_name',
        'category_name',
        'thumbnail_url',
        'card_image_url',
    ];

    protected static function booted()
    {
        static::updating(function ($product) {
            if ($product->isDirty('name')) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::deleting(function ($product) {
            // Delete each image through its model so its files are removed too.
            foreach ($product->images()->get() as $image) {
                $image->delete();
            }

            // New photos live in "<directory>/<product id>/"; clear that folder.
            DB::afterCommit(function () use ($product) {
                Storage::disk(ProductImage::diskName())
                    ->deleteDirectory(ProductImage::pathFor((string) $product->id));
            });
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function getCategoryNameAttribute(): string
    {
        return $this->category?->name ?? 'Uncategorized';
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'product_brand_id');
    }

    public function getBrandNameAttribute(): string
    {
        return $this->brand?->name ?? 'Unbranded';
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    /**
     * Small (300px) picture of the first image: admin tables, POS, thumbnails.
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->images->first()?->thumbnail_url ?? asset('assets/images/general/default-image.webp');
    }

    /**
     * Medium (600px) picture of the first image: storefront product cards.
     */
    public function getCardImageUrlAttribute(): string
    {
        return $this->images->first()?->card_url ?? asset('assets/images/general/default-image.webp');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('track_inventory', false)
                ->orWhere('current_stock', '>', 0);
        });
    }

    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query->sellable()->where('is_new', true)->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->sellable()->where('is_featured', true)->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $searchTerm = strtolower($search);

        return $query->where(function (Builder $q) use ($searchTerm) {
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw('LOWER(measurement) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"]);
        });
    }

    public function tracksInventory(): bool
    {
        return (bool) $this->track_inventory;
    }

    public function hasStockFor(int $quantity): bool
    {
        if (! $this->tracksInventory()) {
            return true;
        }

        return $this->current_stock >= $quantity;
    }

    private function ensureInventoryIsTracked(): void
    {
        if (! $this->tracksInventory()) {
            throw StockTrackingDisabledException::forProduct($this);
        }
    }

    public function addStock(
        int $quantity,
        InventoryMovementTypes $type,
        string $source,
        ?string $notes = null,
        ?array $metadata = null
    ): InventoryMovement {
        $this->ensureInventoryIsTracked();

        return DB::transaction(function () use ($quantity, $type, $source, $notes, $metadata) {
            $locked = static::query()->lockForUpdate()->find($this->id);

            $before = $locked->current_stock;
            $after = $before + $quantity;

            $locked->update(['current_stock' => $after]);
            $this->current_stock = $after;

            return $locked->inventoryMovements()->create([
                'type' => $type->value,
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'source' => $source,
                'notes' => $notes,
                'metadata' => $metadata,
                'created_by' => $source === 'manual' ? Auth::id() : null,
            ]);
        });
    }

    public function updateStock(int $new_quantity, InventoryMovementTypes $type, string $source, ?string $notes = null, ?array $metadata = null): InventoryMovement
    {
        $this->ensureInventoryIsTracked();

        return DB::transaction(function () use ($new_quantity, $type, $source, $notes, $metadata) {
            // Re-fetch with a row lock so no one else can change stock in parallel
            $locked = static::query()->lockForUpdate()->find($this->id);

            $before = $locked->current_stock;
            $quantity_change = $new_quantity - $before;

            $locked->update(['current_stock' => $new_quantity]);
            $this->current_stock = $new_quantity;

            return $locked->inventoryMovements()->create([
                'type' => $type->value,
                'quantity' => $quantity_change,
                'quantity_before' => $before,
                'quantity_after' => $new_quantity,
                'source' => $source,
                'notes' => $notes,
                'metadata' => $metadata,
                'created_by' => $source === 'manual' ? Auth::id() : null,
            ]);
        });
    }

    public function removeStock(
        int $quantity,
        InventoryMovementTypes $type,
        string $source,
        ?string $notes = null,
        ?array $metadata = null,
    ): InventoryMovement {
        $this->ensureInventoryIsTracked();

        return DB::transaction(function () use ($quantity, $type, $source, $notes, $metadata) {
            // Re-fetch with a row lock so no one else can change stock in parallel
            $locked = static::query()->lockForUpdate()->find($this->id);

            $before = $locked->current_stock;
            $after = $before - $quantity;

            if ($after < 0) {
                throw new InsufficientStockException(
                    "Only {$before} units available for '{$locked->name}'"
                );
            }

            $locked->update(['current_stock' => $after]);
            $this->current_stock = $after;

            return $locked->inventoryMovements()->create([
                'type' => $type->value,
                'quantity' => -$quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'source' => $source,
                'notes' => $notes,
                'metadata' => $metadata,
                'created_by' => $source === 'manual' ? Auth::id() : null,
            ]);
        });
    }
}
