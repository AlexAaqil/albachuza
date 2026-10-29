<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url', 'card_url', 'thumbnail_url'];

    protected static function booted(): void
    {
        // Remove the files once the row is really gone. Waiting for the commit means
        // a rolled-back request never leaves rows pointing at deleted files.
        static::deleted(function (ProductImage $image) {
            DB::afterCommit(fn () => $image->deleteFiles());
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Full-size image (used on the product page).
     */
    public function getImageUrlAttribute(): string
    {
        return $this->urlFor($this->name);
    }

    /**
     * Medium image (storefront grid and carousel). Older photos fall back to full-size.
     */
    public function getCardUrlAttribute(): string
    {
        return $this->urlFor($this->card_name ?: $this->name);
    }

    /**
     * Small image (admin tables, POS, thumbnails). Older photos fall back to what exists.
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->urlFor($this->thumb_name ?: $this->card_name ?: $this->name);
    }

    /**
     * Every file that belongs to this image, relative to the disk root.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        return collect([$this->name, $this->card_name, $this->thumb_name])
            ->filter()
            ->unique()
            ->map(fn (string $name) => self::pathFor($name))
            ->values()
            ->all();
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk(self::diskName());

        foreach ($this->paths() as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    public static function diskName(): string
    {
        return config('product_images.disk', 'public');
    }

    public static function pathFor(string $name): string
    {
        return trim(config('product_images.directory', 'products').'/'.$name, '/');
    }

    protected function urlFor(?string $name): string
    {
        if (! $name) {
            return asset('images/default-image.png');
        }

        return asset('storage/'.self::pathFor($name));
    }
}