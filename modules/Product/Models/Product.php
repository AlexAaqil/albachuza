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

        $search_term = strtolower(trim($search));
        $like = "%{$search_term}%";

        return $query->where(function (Builder $q) use ($search_term, $like) {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                ->orWhereRaw('LOWER(measurement) LIKE ?', [$like])
                ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                ->orWhereRaw('CAST(price AS TEXT) LIKE ?', [$like])
                ->orWhereRaw('CAST(cost_price AS TEXT) LIKE ?', [$like]);
        });
    }

    /**
     * IDs of products that share an identity key with at least one other product.
     *
     * A product is considered a duplicate when it matches another on SKU/barcode
     * (hard match), or on name + measurement + price + cost_price + brand + category
     * (soft match). Different sizes/prices of the same shoe are NOT duplicates.
     *
     * @return array<int, int>
     */
    public static function duplicateIds(): array
    {
        $groups = [];

        static::query()
            ->select([
                'id', 'name', 'measurement', 'price', 'cost_price',
                'product_brand_id', 'product_category_id', 'sku', 'barcode',
            ])
            ->each(function (self $product) use (&$groups) {
                $key = $product->duplicateKey();
                if ($key !== null) {
                    $groups[$key][] = $product->id;
                }
            });

        return collect($groups)
            ->filter(fn (array $ids) => count($ids) > 1)
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A normalized identity key for this product, or null when the product
     * has nothing meaningful to group on.
     */
    public function duplicateKey(): ?string
    {
        // Hard duplicate: same SKU or barcode, when set.
        if (! empty($this->sku)) {
            return 'sku:'.strtolower(trim($this->sku));
        }
        if (! empty($this->barcode)) {
            return 'barcode:'.trim($this->barcode);
        }

        // Soft duplicate: same identity fields.
        $name = $this->normalizeForComparison($this->name);
        if ($name === '') {
            return null;
        }

        return 'soft:'.md5(implode('|', [
            $name,
            $this->normalizeForComparison($this->measurement),
            $this->normalizePriceForComparison($this->price),
            $this->normalizePriceForComparison($this->cost_price),
            $this->product_brand_id ?? '',
            $this->product_category_id ?? '',
        ]));
    }

    protected function normalizeForComparison(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower((string) $value)));
    }

    protected function normalizePriceForComparison($value): string
    {
        return $value === null ? '' : number_format((float) $value, 2, '.', '');
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
