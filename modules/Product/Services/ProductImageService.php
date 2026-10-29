<?php

namespace Modules\Product\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

/**
 * The one place that turns a photo into stored product images.
 *
 * Every photo becomes a square, padded (never cropped) WebP in three sizes:
 * full, card and thumb (see config/product_images.php). Anything that adds
 * photos to a product (the product form, the bulk importer, ...) should go
 * through this class so they all behave the same way.
 */
class ProductImageService
{
    /**
     * Files written by this instance that have not been confirmed yet. If the
     * surrounding database transaction fails, discardWritten() removes them.
     *
     * @var array<int, string>
     */
    protected array $written = [];

    // ---------------------------------------------------------------------
    // Limits
    // ---------------------------------------------------------------------

    public function maxPerProduct(): int
    {
        return (int) config('product_images.max_per_product', 5);
    }

    /**
     * Make sure a product can take $incoming more photos.
     *
     * @throws ValidationException
     */
    public function ensureRoom(Product $product, int $incoming): void
    {
        $max = $this->maxPerProduct();

        if ($product->images()->count() + $incoming > $max) {
            throw ValidationException::withMessages([
                'images' => "A product can have at most {$max} images.",
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Adding images
    // ---------------------------------------------------------------------

    /**
     * Store several uploaded files, in order, after the product's existing images.
     *
     * @param  array<int|string, mixed>  $files  Items that are not UploadedFile are skipped.
     * @return array<int|string, ProductImage> The created images, keyed like $files.
     */
    public function storeUploads(Product $product, array $files): array
    {
        $files = array_filter($files, fn ($file) => $file instanceof UploadedFile);

        if ($files === []) {
            return [];
        }

        $this->ensureRoom($product, count($files));

        $sortOrder = $this->lastSortOrder($product);
        $created = [];

        foreach ($files as $key => $file) {
            $created[$key] = $this->storeUpload($product, $file, ++$sortOrder);
        }

        return $created;
    }

    public function storeUpload(Product $product, UploadedFile $file, ?int $sortOrder = null): ProductImage
    {
        return $this->storeBytes($product, $file->get(), $sortOrder);
    }

    /**
     * Store a photo given as raw bytes (an upload, a file from a ZIP, ...).
     *
     * @throws InvalidArgumentException when the bytes are not a usable image
     */
    public function storeBytes(Product $product, string $bytes, ?int $sortOrder = null): ProductImage
    {
        $variants = $this->makeVariants($bytes);

        $base = $product->id.'/'.Str::uuid();
        $names = [
            'full' => "{$base}.webp",
            'card' => "{$base}_card.webp",
            'thumb' => "{$base}_thumb.webp",
        ];

        foreach ($names as $variant => $name) {
            $this->put($name, $variants[$variant]);
        }

        return $product->images()->create([
            'name' => $names['full'],
            'card_name' => $names['card'],
            'thumb_name' => $names['thumb'],
            'sort_order' => $sortOrder ?? $this->lastSortOrder($product) + 1,
        ]);
    }

    // ---------------------------------------------------------------------
    // Processing
    // ---------------------------------------------------------------------

    /**
     * Build the WebP variants for a photo.
     *
     * The photo is rotated according to its EXIF data, scaled down to fit and
     * centred on a square canvas. It is never cropped and never enlarged.
     *
     * @param  array<int, string>|null  $only  Limit to some variants, e.g. ['card', 'thumb'].
     * @return array<string, string> Variant name => WebP bytes.
     *
     * @throws InvalidArgumentException
     */
    public function makeVariants(string $bytes, ?array $only = null): array
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw new InvalidArgumentException('The file is not a valid image.');
        }

        [$width, $height] = $info;
        $longest = max($width, $height);
        $maxDimension = (int) config('product_images.max_dimension', 6000);

        if ($longest > $maxDimension) {
            throw new InvalidArgumentException(
                "The image is too large ({$width}x{$height}). The longest side can be at most {$maxDimension}px."
            );
        }

        $variants = config('product_images.variants');
        $background = config('product_images.background', 'ffffff');

        if ($only !== null) {
            $variants = array_intersect_key($variants, array_flip($only));
        }

        return $this->withMemoryLimit(function () use ($bytes, $variants, $background, $longest) {
            // Smallest canvas that fits the biggest variant, without enlarging the photo.
            $biggest = min($longest, max(array_column($variants, 'size')));

            // One lossless pass, so the smaller variants are made from this
            // instead of decoding a large photo again for every variant.
            $base = Image::fromBytes($bytes)
                ->orient()
                ->contain($biggest, $biggest, $background)
                ->toPng()
                ->toBytes();

            $result = [];

            foreach ($variants as $name => $settings) {
                $side = min($biggest, (int) $settings['size']);

                $result[$name] = Image::fromBytes($base)
                    ->contain($side, $side, $background)
                    ->quality((int) $settings['quality'])
                    ->toWebp()
                    ->toBytes();
            }

            return $result;
        });
    }

    // ---------------------------------------------------------------------
    // Removing and ordering
    // ---------------------------------------------------------------------

    /**
     * Delete some of a product's images. The files are removed once the
     * surrounding transaction commits.
     *
     * @param  array<int, int|string>  $ids
     */
    public function deleteForProduct(Product $product, array $ids): int
    {
        $count = 0;

        foreach ($product->images()->whereIn('id', $ids)->get() as $image) {
            $image->delete();
            $count++;
        }

        return $count;
    }

    /**
     * Apply an order sent by the form.
     *
     * Each entry is either the id of an existing image ("12") or "new-N", the
     * position of a file in the same request's uploads. Images that are not
     * mentioned keep their relative order after the listed ones.
     *
     * @param  array<int, string>  $order
     * @param  array<int|string, ProductImage>  $created  Result of storeUploads().
     */
    public function applyOrder(Product $product, array $order, array $created = []): void
    {
        $images = $product->images()->get()->keyBy('id');
        $ids = [];

        foreach ($order as $token) {
            $token = (string) $token;
            $image = null;

            if (str_starts_with($token, 'new-')) {
                $image = $created[(int) substr($token, 4)] ?? null;
            } elseif (ctype_digit($token)) {
                $image = $images->get((int) $token);
            }

            if ($image && ! in_array($image->id, $ids, true)) {
                $ids[] = $image->id;
            }
        }

        foreach ($images->keys() as $id) {
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        foreach ($ids as $position => $id) {
            ProductImage::whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }

    // ---------------------------------------------------------------------
    // Maintenance
    // ---------------------------------------------------------------------

    /**
     * Create the card and thumb files for a photo that only has a full-size
     * file (photos uploaded before variants existed). The full-size file is
     * left untouched.
     *
     * @return bool true when something was generated
     */
    public function regenerate(ProductImage $image, bool $force = false): bool
    {
        if (! $force && $image->card_name && $image->thumb_name) {
            return false;
        }

        $disk = Storage::disk(ProductImage::diskName());
        $source = ProductImage::pathFor($image->name);

        if (! $image->name || ! $disk->exists($source)) {
            return false;
        }

        $variants = $this->makeVariants($disk->get($source), ['card', 'thumb']);

        $stem = preg_replace('/\.[^.\/]+$/', '', $image->name);
        $cardName = "{$stem}_card.webp";
        $thumbName = "{$stem}_thumb.webp";

        $this->put($cardName, $variants['card']);
        $this->put($thumbName, $variants['thumb']);

        $image->update(['card_name' => $cardName, 'thumb_name' => $thumbName]);

        return true;
    }

    /**
     * Remove files written by this instance. Call this when the request that
     * stored them fails and its database changes are rolled back.
     */
    public function discardWritten(): void
    {
        $disk = Storage::disk(ProductImage::diskName());

        foreach ($this->written as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $this->written = [];
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    protected function put(string $name, string $contents): void
    {
        $path = ProductImage::pathFor($name);

        Storage::disk(ProductImage::diskName())->put($path, $contents);

        $this->written[] = $path;
    }

    protected function lastSortOrder(Product $product): int
    {
        return (int) $product->images()->max('sort_order');
    }

    /**
     * Run a callback with a higher memory limit, then restore the old one.
     */
    protected function withMemoryLimit(callable $callback): mixed
    {
        $wanted = config('product_images.memory_limit');
        $current = ini_get('memory_limit');

        if (! $wanted || $current === '-1' || $this->shorthandToBytes($current) >= $this->shorthandToBytes($wanted)) {
            return $callback();
        }

        ini_set('memory_limit', $wanted);

        try {
            return $callback();
        } finally {
            ini_set('memory_limit', $current);
        }
    }

    protected function shorthandToBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}