diff --git a/config/product_images.php b/config/product_images.php
new file mode 100644
index 0000000..ee626e2
--- /dev/null
+++ b/config/product_images.php
@@ -0,0 +1,52 @@
+<?php
+
+return [
+
+    /*
+    |--------------------------------------------------------------------------
+    | Storage
+    |--------------------------------------------------------------------------
+    | Product photos are stored on this filesystem disk, under this directory.
+    | New photos are saved in "<directory>/<product id>/<uuid>.webp".
+    */
+    'disk' => env('PRODUCT_IMAGES_DISK', 'public'),
+    'directory' => 'products',
+
+    /*
+    |--------------------------------------------------------------------------
+    | Shape
+    |--------------------------------------------------------------------------
+    | Every photo is placed on a square canvas with this background colour so
+    | that nothing is ever cropped (important for shoes: toes and soles).
+    | Small photos are never enlarged, the canvas is only as big as the photo.
+    */
+    'background' => 'ffffff',
+
+    /*
+    |--------------------------------------------------------------------------
+    | Limits
+    |--------------------------------------------------------------------------
+    | The browser shrinks photos before uploading, so these limits are only a
+    | safety net for uploads that skip that step (e.g. bulk import).
+    */
+    'max_per_product' => 5,
+    'max_upload_kb' => 10240,   // 10 MB
+    'max_dimension' => 6000,    // longest side in pixels (24 megapixel photo)
+
+    // Raised temporarily while an image is processed (GD needs ~4 bytes/pixel).
+    'memory_limit' => '512M',
+
+    /*
+    |--------------------------------------------------------------------------
+    | Variants
+    |--------------------------------------------------------------------------
+    | size    : side length of the square canvas, in pixels
+    | quality : WebP quality, 1-100
+    */
+    'variants' => [
+        'full' => ['size' => 1200, 'quality' => 82], // product page
+        'card' => ['size' => 600,  'quality' => 80], // storefront grid / carousel
+        'thumb' => ['size' => 300, 'quality' => 75], // admin tables, POS, thumbnails
+    ],
+
+];
diff --git a/modules/Order/Http/Controllers/OrderController.php b/modules/Order/Http/Controllers/OrderController.php
index d39f174..d06b81f 100644
--- a/modules/Order/Http/Controllers/OrderController.php
+++ b/modules/Order/Http/Controllers/OrderController.php
@@ -61,6 +61,7 @@ public function index(Request $request)
     public function create()
     {
         $products = Product::query()
+            ->with('images')
             ->sellable()
             ->where('is_active', true)
             ->orderBy('name')
diff --git a/modules/Product/Console/RegenerateProductImages.php b/modules/Product/Console/RegenerateProductImages.php
new file mode 100644
index 0000000..217e37f
--- /dev/null
+++ b/modules/Product/Console/RegenerateProductImages.php
@@ -0,0 +1,62 @@
+<?php
+
+namespace Modules\Product\Console;
+
+use Illuminate\Console\Command;
+use Modules\Product\Models\ProductImage;
+use Modules\Product\Services\ProductImageService;
+use Throwable;
+
+class RegenerateProductImages extends Command
+{
+    protected $signature = 'product-images:regenerate
+                            {--force : Rebuild the card and thumb files even if they already exist}';
+
+    protected $description = 'Create the card and thumb versions for product photos that only have a full-size file';
+
+    public function handle(ProductImageService $images): int
+    {
+        $query = ProductImage::query()->orderBy('id');
+
+        if (! $this->option('force')) {
+            $query->where(fn ($q) => $q->whereNull('card_name')->orWhereNull('thumb_name'));
+        }
+
+        $total = (clone $query)->count();
+
+        if ($total === 0) {
+            $this->info('Nothing to do: every product photo already has its card and thumb versions.');
+
+            return self::SUCCESS;
+        }
+
+        $this->info("Processing {$total} photo(s)...");
+
+        $done = 0;
+        $skipped = 0;
+        $failed = 0;
+
+        $bar = $this->output->createProgressBar($total);
+        $bar->start();
+
+        $query->chunkById(50, function ($chunk) use ($images, &$done, &$skipped, &$failed, $bar) {
+            foreach ($chunk as $image) {
+                try {
+                    $images->regenerate($image, (bool) $this->option('force')) ? $done++ : $skipped++;
+                } catch (Throwable $e) {
+                    $failed++;
+                    $this->newLine();
+                    $this->warn("Image #{$image->id} ({$image->name}): {$e->getMessage()}");
+                }
+
+                $bar->advance();
+            }
+        });
+
+        $bar->finish();
+        $this->newLine(2);
+        $this->info("Done. Generated: {$done}, skipped (file missing): {$skipped}, failed: {$failed}.");
+
+        return $failed > 0 ? self::FAILURE : self::SUCCESS;
+    }
+}
diff --git a/modules/Product/Database/Migrations/2026_10_27_100000_add_variants_to_product_images_table.php b/modules/Product/Database/Migrations/2026_10_27_100000_add_variants_to_product_images_table.php
new file mode 100644
index 0000000..05859e2
--- /dev/null
+++ b/modules/Product/Database/Migrations/2026_10_27_100000_add_variants_to_product_images_table.php
@@ -0,0 +1,33 @@
+<?php
+
+use Illuminate\Database\Migrations\Migration;
+use Illuminate\Database\Schema\Blueprint;
+use Illuminate\Support\Facades\Schema;
+
+return new class extends Migration
+{
+    /**
+     * Run the migrations.
+     *
+     * "name" keeps pointing at the full-size file, so existing rows keep working.
+     * The two new columns are NULL for older photos until they are regenerated
+     * with: php artisan product-images:regenerate
+     */
+    public function up(): void
+    {
+        Schema::table('product_images', function (Blueprint $table) {
+            $table->string('card_name')->nullable()->after('name');
+            $table->string('thumb_name')->nullable()->after('card_name');
+        });
+    }
+
+    /**
+     * Reverse the migrations.
+     */
+    public function down(): void
+    {
+        Schema::table('product_images', function (Blueprint $table) {
+            $table->dropColumn(['card_name', 'thumb_name']);
+        });
+    }
+};
diff --git a/modules/Product/Http/Controllers/ProductController.php b/modules/Product/Http/Controllers/ProductController.php
index de5ef28..f7d0a4f 100644
--- a/modules/Product/Http/Controllers/ProductController.php
+++ b/modules/Product/Http/Controllers/ProductController.php
@@ -5,8 +5,7 @@
 use App\Http\Controllers\Controller;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\DB;
-use Illuminate\Support\Facades\Storage;
-use Illuminate\Support\Str;
+use Illuminate\Validation\ValidationException;
 use Inertia\Inertia;
 use Exception;
 use Modules\Product\Models\Product;
@@ -14,13 +13,16 @@
 use Modules\Product\Models\ProductBrand;
 use Modules\Product\Http\Resources\ProductIndexPageResource;
 use Modules\Product\Http\Requests\ProductRequest;
-use Illuminate\Support\Facades\Image;
+use Modules\Product\Services\ProductImageService;
 
 class ProductController extends Controller
 {
+    public function __construct(protected ProductImageService $productImages) {}
+
     public function index(Request $request)
     {
         $query = Product::query()
+            ->with(['images', 'category', 'brand'])
             ->search($request->search);
 
         if ($request->filled('status')) {
@@ -97,7 +99,7 @@ public function store(ProductRequest $request)
         $validated_data = $request->validated();
 
         $images = $validated_data['images'] ?? [];
-        unset($validated_data['images']);
+        unset($validated_data['images'], $validated_data['image_order']);
 
         try {
             DB::beginTransaction();
@@ -105,7 +107,7 @@ public function store(ProductRequest $request)
             $product = Product::create($validated_data);
 
             if (!empty($images)) {
-                $this->uploadImages($images, $product);
+                $this->productImages->storeUploads($product, $images);
             }
 
             DB::commit();
@@ -122,6 +124,7 @@ public function store(ProductRequest $request)
             return to_route('products.index');
         } catch (Exception $e) {
             DB::rollback();
+            $this->productImages->discardWritten();
 
             Inertia::flash('toast', [
                 'type' => "error",
@@ -151,7 +154,8 @@ public function update(ProductRequest $request, Product $product)
         $validated = $request->validated();
 
         $images = $validated['images'] ?? [];
-        unset($validated['images']);
+        $imageOrder = $validated['image_order'] ?? [];
+        unset($validated['images'], $validated['image_order']);
 
         $imagesToDelete = $request->input('images_to_delete', []);
         if (is_string($imagesToDelete)) {
@@ -166,23 +170,18 @@ public function update(ProductRequest $request, Product $product)
 
             $product->update($validated);
 
-            // DELETE ONLY SELECTED IMAGES
+            // Remove the images the user took out (their files go once this is committed)
             if (!empty($imagesToDelete)) {
-                $oldImages = $product->images()->whereIn('id', $imagesToDelete)->get();
-
-                foreach ($oldImages as $image) {
-                    if (Storage::disk('public')->exists("products/{$image->name}")) {
-                        Storage::disk('public')->delete("products/{$image->name}");
-                    }
-                    $image->delete();
-                }
+                $this->productImages->deleteForProduct($product, $imagesToDelete);
             }
 
-            // NEXT SORT ORDER (IMPORTANT FIX)
-            $nextSortOrder = $product->images()->max('sort_order') ?? 0;
+            // Add new uploads after the remaining ones, then apply the order from the form
+            $created = !empty($images)
+                ? $this->productImages->storeUploads($product, $images)
+                : [];
 
-            if (!empty($images)) {
-                $this->uploadImages($images, $product, $nextSortOrder);
+            if (!empty($imageOrder)) {
+                $this->productImages->applyOrder($product, $imageOrder, $created);
             }
 
             DB::commit();
@@ -204,8 +203,14 @@ public function update(ProductRequest $request, Product $product)
             ]);
 
             return to_route('products.index');
+        } catch (ValidationException $e) {
+            DB::rollBack();
+            $this->productImages->discardWritten();
+
+            throw $e;
         } catch (\Throwable $e) {
             DB::rollBack();
+            $this->productImages->discardWritten();
 
             Inertia::flash('toast', [
                 'type' => "error",
@@ -256,33 +261,4 @@ public function destroy(Product $product)
         
         return to_route('products.index');
     }
-
-    protected function uploadImages(array $images, Product $product, int $startSortOrder = 0): void
-    {
-        $slug = Str::slug($product->name);
-        $timestamp = now()->format('Ymd_His');
-        $productId = $product->id;
-
-        foreach ($images as $index => $image) {
-            if (!$image instanceof \Illuminate\Http\UploadedFile) {
-                continue;
-            }
-
-            // $extension = $image->getClientOriginalExtension();
-            $random = Str::random(6);
-            $sortOrder = $startSortOrder + $index + 1;
-
-            $filename = "{$slug}_{$productId}_{$index}_{$timestamp}_{$random}.webp";
-
-            Image::fromUpload($image)
-                ->contain(800, 800, 'ffffff')
-                ->toWebp()
-                ->storeAs('products', $filename, 'public');
-            
-            $product->images()->create([
-                'name' => $filename,
-                'sort_order' => $sortOrder,
-            ]);
-        }
-    }
-}
\ No newline at end of file
+}
diff --git a/modules/Product/Http/Controllers/ProductInventoryController.php b/modules/Product/Http/Controllers/ProductInventoryController.php
index 01bbbe1..2b1d156 100644
--- a/modules/Product/Http/Controllers/ProductInventoryController.php
+++ b/modules/Product/Http/Controllers/ProductInventoryController.php
@@ -54,7 +54,7 @@ public function index(Request $request)
             }
         }
         
-        $products = $query->orderBy('name')->paginate(20);
+        $products = $query->with(['images', 'category', 'brand'])->orderBy('name')->paginate(20);
         
         // Get summary stats
         $stats = [
diff --git a/modules/Product/Http/Requests/ProductRequest.php b/modules/Product/Http/Requests/ProductRequest.php
index a37eccf..a13ebac 100644
--- a/modules/Product/Http/Requests/ProductRequest.php
+++ b/modules/Product/Http/Requests/ProductRequest.php
@@ -23,6 +23,9 @@ public function authorize(): bool
      */
     public function rules(): array
     {
+        $maxImages = config('product_images.max_per_product');
+        $maxDimension = config('product_images.max_dimension');
+
         return [
             'name' => [
                 'required', 
@@ -56,21 +59,36 @@ public function rules(): array
             'current_stock' => ['prohibited'],
             'product_category_id' => ['nullable', 'exists:product_categories,id'],
             'product_brand_id' => ['nullable', 'exists:product_brands,id'],
-            'images' => ['nullable', 'array', 'max:5'],
-            'images.*' => ['image', 'mimes:jpg,jpeg,png,gif,svg,webp', 'max:2048'],
+            'images' => ['nullable', 'array', 'max:' . $maxImages],
+            'images.*' => [
+                'image',
+                'mimes:jpg,jpeg,png,gif,webp',
+                'max:' . config('product_images.max_upload_kb'),
+                'dimensions:max_width=' . $maxDimension . ',max_height=' . $maxDimension,
+            ],
+            // Final order of the images, sent by the edit form: an existing image id ("12")
+            // or the position of a new upload ("new-0").
+            'image_order' => ['nullable', 'array', 'max:' . $maxImages],
+            'image_order.*' => ['string', 'regex:/^(\d+|new-\d+)$/'],
         ];
     }
 
     public function messages(): array
     {
+        $maxImages = config('product_images.max_per_product');
+        $maxMb = round(config('product_images.max_upload_kb') / 1024);
+        $maxDimension = config('product_images.max_dimension');
+
         return [
             'name.required' => 'A product must have a name',
             'price.required' => 'The product price is required',
             'sku.unique' => 'This SKU is already in use. Please use a different one.',
             'barcode.unique' => 'This barcode is already in use.',
-            'images.max' => 'You can upload a maximum of 5 images.',
-            'images.*.max' => 'Each image must be less than 2MB.',
-            'images.*.mimes' => 'Only JPG, JPEG, PNG, GIF, SVG and WEBP images are allowed.',
+            'images.max' => "You can upload a maximum of {$maxImages} images.",
+            'images.*.max' => "Each image must be less than {$maxMb}MB.",
+            'images.*.mimes' => 'Only JPG, PNG, GIF and WEBP images are allowed.',
+            'images.*.image' => 'Each file must be an image.',
+            'images.*.dimensions' => "Each image can be at most {$maxDimension}px wide or tall.",
         ];
     }
 }
diff --git a/modules/Product/Http/Resources/ProductDetailsResource.php b/modules/Product/Http/Resources/ProductDetailsResource.php
index 95fde40..1cd53f8 100644
--- a/modules/Product/Http/Resources/ProductDetailsResource.php
+++ b/modules/Product/Http/Resources/ProductDetailsResource.php
@@ -31,7 +31,9 @@ public function toArray(Request $request): array
             'images' => $this->whenLoaded('images', function() {
                 return $this->images->map(function($image) {
                     return [
-                        'url' => asset('storage/products/' . $image->name),
+                        'url' => $image->image_url,        // full size
+                        'card_url' => $image->card_url,    // medium
+                        'thumb_url' => $image->thumbnail_url, // small
                         'alt' => $this->slug,
                     ];
                 })->values()->toArray();
diff --git a/modules/Product/Http/Resources/ProductHomePageResource.php b/modules/Product/Http/Resources/ProductHomePageResource.php
index d8c1d50..c1e0f2d 100644
--- a/modules/Product/Http/Resources/ProductHomePageResource.php
+++ b/modules/Product/Http/Resources/ProductHomePageResource.php
@@ -35,7 +35,9 @@ public function toArray(Request $request): array
             'images' => $this->whenLoaded('images', function() {
                 return $this->images->map(function($image) {
                     return [
-                        'url' => asset('storage/products/' . $image->name),
+                        'url' => $image->image_url,        // full size
+                        'card_url' => $image->card_url,    // medium
+                        'thumb_url' => $image->thumbnail_url, // small
                         'alt' => $this->slug,
                     ];
                 })->values()->toArray();
diff --git a/modules/Product/Http/Resources/ProductIndexPageResource.php b/modules/Product/Http/Resources/ProductIndexPageResource.php
index a9ef8b1..f42039b 100644
--- a/modules/Product/Http/Resources/ProductIndexPageResource.php
+++ b/modules/Product/Http/Resources/ProductIndexPageResource.php
@@ -28,6 +28,7 @@ public function toArray(Request $request): array
             'discount_display' => $this->discount_display,
             'discounted_price' => $this->discounted_price,
             'thumbnail_url' => $this->thumbnail_url,
+            'card_image_url' => $this->card_image_url,
             'category_name' => $this->category_name,
             'brand_name' => $this->brand_name,
             'description' => $this->description,
diff --git a/modules/Product/Models/Product.php b/modules/Product/Models/Product.php
index aaee395..35bbc6f 100644
--- a/modules/Product/Models/Product.php
+++ b/modules/Product/Models/Product.php
@@ -38,61 +38,28 @@ class Product extends Model
         'brand_name',
         'category_name',
         'thumbnail_url',
+        'card_image_url',
     ];
 
     protected static function booted()
     {
         static::updating(function ($product) {
             if ($product->isDirty('name')) {
-                $old_name = $product->getOriginal('name');
-                $new_name = $product->name;
-                
-                // Update slug
-                $product->slug = Str::slug($new_name);
-                
-                // Get images directly from database
-                $images = $product->images()->get();
-                
-                $new_slug = Str::slug($new_name);
-                
-                // Rename all images
-                foreach ($images as $image) {
-                    $old_filename = $image->name;
-                    
-                    // Extract the current slug from the filename (first part before first underscore)
-                    $parts = explode('_', $old_filename);
-                    $old_slug = $parts[0];
-                    
-                    // Replace old slug with new slug
-                    $new_filename = str_replace($old_slug, $new_slug, $old_filename);
-                    
-                    if ($old_filename !== $new_filename) {
-                        $old_path = 'products/' . $old_filename;
-                        $new_path = 'products/' . $new_filename;
-                        
-                        // Rename the actual file
-                        if (Storage::disk('public')->exists($old_path)) {
-                            Storage::disk('public')->move($old_path, $new_path);
-                        }
-                        
-                        // Update database record
-                        $image->name = $new_filename;
-                        $image->save();
-                    }
-                }
+                $product->slug = Str::slug($product->name);
             }
         });
 
         static::deleting(function ($product) {
-            $images = $product->images()->get();
-            
-            foreach ($images as $image) {
-                $path = "products/{$image->name}";
-                if (Storage::disk('public')->exists($path)) {
-                    Storage::disk('public')->delete($path);
-                }
+            // Delete each image through its model so its files are removed too.
+            foreach ($product->images()->get() as $image) {
                 $image->delete();
             }
+
+            // New photos live in "<directory>/<product id>/"; clear that folder.
+            DB::afterCommit(function () use ($product) {
+                Storage::disk(ProductImage::diskName())
+                    ->deleteDirectory(ProductImage::pathFor((string) $product->id));
+            });
         });
     }
 
@@ -121,9 +88,20 @@ public function images(): HasMany
         return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
     }
 
+    /**
+     * Small (300px) picture of the first image: admin tables, POS, thumbnails.
+     */
     public function getThumbnailUrlAttribute(): string
     {
-        return $this->images->first()?->image_url ?? asset('assets/images/general/default-image.webp');
+        return $this->images->first()?->thumbnail_url ?? asset('assets/images/general/default-image.webp');
+    }
+
+    /**
+     * Medium (600px) picture of the first image: storefront product cards.
+     */
+    public function getCardImageUrlAttribute(): string
+    {
+        return $this->images->first()?->card_url ?? asset('assets/images/general/default-image.webp');
     }
 
     public function inventoryMovements(): HasMany
diff --git a/modules/Product/Models/ProductImage.php b/modules/Product/Models/ProductImage.php
index 3867815..6384ca1 100644
--- a/modules/Product/Models/ProductImage.php
+++ b/modules/Product/Models/ProductImage.php
@@ -4,33 +4,95 @@
 
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;
+use Illuminate\Support\Facades\DB;
+use Illuminate\Support\Facades\Storage;
 
 class ProductImage extends Model
 {
     protected $guarded = [];
 
-    protected $appends = ['image_url', 'thumbnail_url'];
+    protected $appends = ['image_url', 'card_url', 'thumbnail_url'];
+
+    protected static function booted(): void
+    {
+        // Remove the files once the row is really gone. Waiting for the commit means
+        // a rolled-back request never leaves rows pointing at deleted files.
+        static::deleted(function (ProductImage $image) {
+            DB::afterCommit(fn () => $image->deleteFiles());
+        });
+    }
 
     public function product(): BelongsTo
     {
         return $this->belongsTo(Product::class, 'product_id');
     }
 
+    /**
+     * Full-size image (used on the product page).
+     */
     public function getImageUrlAttribute(): string
     {
-        if (!$this->name) {
-            return asset('images/default-image.png');
-        }
+        return $this->urlFor($this->name);
+    }
 
-        return asset("storage/products/{$this->name}");
+    /**
+     * Medium image (storefront grid and carousel). Older photos fall back to full-size.
+     */
+    public function getCardUrlAttribute(): string
+    {
+        return $this->urlFor($this->card_name ?: $this->name);
     }
 
+    /**
+     * Small image (admin tables, POS, thumbnails). Older photos fall back to what exists.
+     */
     public function getThumbnailUrlAttribute(): string
     {
-        if (!$this->name) {
+        return $this->urlFor($this->thumb_name ?: $this->card_name ?: $this->name);
+    }
+
+    /**
+     * Every file that belongs to this image, relative to the disk root.
+     *
+     * @return array<int, string>
+     */
+    public function paths(): array
+    {
+        return collect([$this->name, $this->card_name, $this->thumb_name])
+            ->filter()
+            ->unique()
+            ->map(fn (string $name) => self::pathFor($name))
+            ->values()
+            ->all();
+    }
+
+    public function deleteFiles(): void
+    {
+        $disk = Storage::disk(self::diskName());
+
+        foreach ($this->paths() as $path) {
+            if ($disk->exists($path)) {
+                $disk->delete($path);
+            }
+        }
+    }
+
+    public static function diskName(): string
+    {
+        return config('product_images.disk', 'public');
+    }
+
+    public static function pathFor(string $name): string
+    {
+        return trim(config('product_images.directory', 'products').'/'.$name, '/');
+    }
+
+    protected function urlFor(?string $name): string
+    {
+        if (! $name) {
             return asset('images/default-image.png');
         }
 
-        return asset("storage/products/{$this->name}");
+        return asset('storage/'.self::pathFor($name));
     }
-}
\ No newline at end of file
+}
diff --git a/modules/Product/Providers/ProductServiceProvider.php b/modules/Product/Providers/ProductServiceProvider.php
index 558e62b..a59babf 100644
--- a/modules/Product/Providers/ProductServiceProvider.php
+++ b/modules/Product/Providers/ProductServiceProvider.php
@@ -3,6 +3,7 @@
 namespace Modules\Product\Providers;
 
 use Illuminate\Support\ServiceProvider;
+use Modules\Product\Console\RegenerateProductImages;
 
 class ProductServiceProvider extends ServiceProvider
 {
@@ -13,6 +14,10 @@ public function boot(): void
         // // Merge a custom config file
         //$this->mergeConfigFrom(__DIR__ . '/../config.php', 'user');
 
+        if ($this->app->runningInConsole()) {
+            $this->commands([RegenerateProductImages::class]);
+        }
+
         $this->app->register(RouteServiceProvider::class);
     }
 }
\ No newline at end of file
diff --git a/modules/Product/Services/ProductImageService.php b/modules/Product/Services/ProductImageService.php
new file mode 100644
index 0000000..2f85fcf
--- /dev/null
+++ b/modules/Product/Services/ProductImageService.php
@@ -0,0 +1,357 @@
+<?php
+
+namespace Modules\Product\Services;
+
+use Illuminate\Http\UploadedFile;
+use Illuminate\Support\Facades\Image;
+use Illuminate\Support\Facades\Storage;
+use Illuminate\Support\Str;
+use Illuminate\Validation\ValidationException;
+use InvalidArgumentException;
+use Modules\Product\Models\Product;
+use Modules\Product\Models\ProductImage;
+
+/**
+ * The one place that turns a photo into stored product images.
+ *
+ * Every photo becomes a square, padded (never cropped) WebP in three sizes:
+ * full, card and thumb (see config/product_images.php). Anything that adds
+ * photos to a product (the product form, the bulk importer, ...) should go
+ * through this class so they all behave the same way.
+ */
+class ProductImageService
+{
+    /**
+     * Files written by this instance that have not been confirmed yet. If the
+     * surrounding database transaction fails, discardWritten() removes them.
+     *
+     * @var array<int, string>
+     */
+    protected array $written = [];
+
+    // ---------------------------------------------------------------------
+    // Limits
+    // ---------------------------------------------------------------------
+
+    public function maxPerProduct(): int
+    {
+        return (int) config('product_images.max_per_product', 5);
+    }
+
+    /**
+     * Make sure a product can take $incoming more photos.
+     *
+     * @throws ValidationException
+     */
+    public function ensureRoom(Product $product, int $incoming): void
+    {
+        $max = $this->maxPerProduct();
+
+        if ($product->images()->count() + $incoming > $max) {
+            throw ValidationException::withMessages([
+                'images' => "A product can have at most {$max} images.",
+            ]);
+        }
+    }
+
+    // ---------------------------------------------------------------------
+    // Adding images
+    // ---------------------------------------------------------------------
+
+    /**
+     * Store several uploaded files, in order, after the product's existing images.
+     *
+     * @param  array<int|string, mixed>  $files  Items that are not UploadedFile are skipped.
+     * @return array<int|string, ProductImage> The created images, keyed like $files.
+     */
+    public function storeUploads(Product $product, array $files): array
+    {
+        $files = array_filter($files, fn ($file) => $file instanceof UploadedFile);
+
+        if ($files === []) {
+            return [];
+        }
+
+        $this->ensureRoom($product, count($files));
+
+        $sortOrder = $this->lastSortOrder($product);
+        $created = [];
+
+        foreach ($files as $key => $file) {
+            $created[$key] = $this->storeUpload($product, $file, ++$sortOrder);
+        }
+
+        return $created;
+    }
+
+    public function storeUpload(Product $product, UploadedFile $file, ?int $sortOrder = null): ProductImage
+    {
+        return $this->storeBytes($product, $file->get(), $sortOrder);
+    }
+
+    /**
+     * Store a photo given as raw bytes (an upload, a file from a ZIP, ...).
+     *
+     * @throws InvalidArgumentException when the bytes are not a usable image
+     */
+    public function storeBytes(Product $product, string $bytes, ?int $sortOrder = null): ProductImage
+    {
+        $variants = $this->makeVariants($bytes);
+
+        $base = $product->id.'/'.Str::uuid();
+        $names = [
+            'full' => "{$base}.webp",
+            'card' => "{$base}_card.webp",
+            'thumb' => "{$base}_thumb.webp",
+        ];
+
+        foreach ($names as $variant => $name) {
+            $this->put($name, $variants[$variant]);
+        }
+
+        return $product->images()->create([
+            'name' => $names['full'],
+            'card_name' => $names['card'],
+            'thumb_name' => $names['thumb'],
+            'sort_order' => $sortOrder ?? $this->lastSortOrder($product) + 1,
+        ]);
+    }
+
+    // ---------------------------------------------------------------------
+    // Processing
+    // ---------------------------------------------------------------------
+
+    /**
+     * Build the WebP variants for a photo.
+     *
+     * The photo is rotated according to its EXIF data, scaled down to fit and
+     * centred on a square canvas. It is never cropped and never enlarged.
+     *
+     * @param  array<int, string>|null  $only  Limit to some variants, e.g. ['card', 'thumb'].
+     * @return array<string, string> Variant name => WebP bytes.
+     *
+     * @throws InvalidArgumentException
+     */
+    public function makeVariants(string $bytes, ?array $only = null): array
+    {
+        $info = @getimagesizefromstring($bytes);
+
+        if ($info === false) {
+            throw new InvalidArgumentException('The file is not a valid image.');
+        }
+
+        [$width, $height] = $info;
+        $longest = max($width, $height);
+        $maxDimension = (int) config('product_images.max_dimension', 6000);
+
+        if ($longest > $maxDimension) {
+            throw new InvalidArgumentException(
+                "The image is too large ({$width}x{$height}). The longest side can be at most {$maxDimension}px."
+            );
+        }
+
+        $variants = config('product_images.variants');
+        $background = config('product_images.background', 'ffffff');
+
+        if ($only !== null) {
+            $variants = array_intersect_key($variants, array_flip($only));
+        }
+
+        return $this->withMemoryLimit(function () use ($bytes, $variants, $background, $longest) {
+            // Smallest canvas that fits the biggest variant, without enlarging the photo.
+            $biggest = min($longest, max(array_column($variants, 'size')));
+
+            // One lossless pass, so the smaller variants are made from this
+            // instead of decoding a large photo again for every variant.
+            $base = Image::fromBytes($bytes)
+                ->orient()
+                ->contain($biggest, $biggest, $background)
+                ->toPng()
+                ->toBytes();
+
+            $result = [];
+
+            foreach ($variants as $name => $settings) {
+                $side = min($biggest, (int) $settings['size']);
+
+                $result[$name] = Image::fromBytes($base)
+                    ->contain($side, $side, $background)
+                    ->quality((int) $settings['quality'])
+                    ->toWebp()
+                    ->toBytes();
+            }
+
+            return $result;
+        });
+    }
+
+    // ---------------------------------------------------------------------
+    // Removing and ordering
+    // ---------------------------------------------------------------------
+
+    /**
+     * Delete some of a product's images. The files are removed once the
+     * surrounding transaction commits.
+     *
+     * @param  array<int, int|string>  $ids
+     */
+    public function deleteForProduct(Product $product, array $ids): int
+    {
+        $count = 0;
+
+        foreach ($product->images()->whereIn('id', $ids)->get() as $image) {
+            $image->delete();
+            $count++;
+        }
+
+        return $count;
+    }
+
+    /**
+     * Apply an order sent by the form.
+     *
+     * Each entry is either the id of an existing image ("12") or "new-N", the
+     * position of a file in the same request's uploads. Images that are not
+     * mentioned keep their relative order after the listed ones.
+     *
+     * @param  array<int, string>  $order
+     * @param  array<int|string, ProductImage>  $created  Result of storeUploads().
+     */
+    public function applyOrder(Product $product, array $order, array $created = []): void
+    {
+        $images = $product->images()->get()->keyBy('id');
+        $ids = [];
+
+        foreach ($order as $token) {
+            $token = (string) $token;
+            $image = null;
+
+            if (str_starts_with($token, 'new-')) {
+                $image = $created[(int) substr($token, 4)] ?? null;
+            } elseif (ctype_digit($token)) {
+                $image = $images->get((int) $token);
+            }
+
+            if ($image && ! in_array($image->id, $ids, true)) {
+                $ids[] = $image->id;
+            }
+        }
+
+        foreach ($images->keys() as $id) {
+            if (! in_array($id, $ids, true)) {
+                $ids[] = $id;
+            }
+        }
+
+        foreach ($ids as $position => $id) {
+            ProductImage::whereKey($id)->update(['sort_order' => $position + 1]);
+        }
+    }
+
+    // ---------------------------------------------------------------------
+    // Maintenance
+    // ---------------------------------------------------------------------
+
+    /**
+     * Create the card and thumb files for a photo that only has a full-size
+     * file (photos uploaded before variants existed). The full-size file is
+     * left untouched.
+     *
+     * @return bool true when something was generated
+     */
+    public function regenerate(ProductImage $image, bool $force = false): bool
+    {
+        if (! $force && $image->card_name && $image->thumb_name) {
+            return false;
+        }
+
+        $disk = Storage::disk(ProductImage::diskName());
+        $source = ProductImage::pathFor($image->name);
+
+        if (! $image->name || ! $disk->exists($source)) {
+            return false;
+        }
+
+        $variants = $this->makeVariants($disk->get($source), ['card', 'thumb']);
+
+        $stem = preg_replace('/\.[^.\/]+$/', '', $image->name);
+        $cardName = "{$stem}_card.webp";
+        $thumbName = "{$stem}_thumb.webp";
+
+        $this->put($cardName, $variants['card']);
+        $this->put($thumbName, $variants['thumb']);
+
+        $image->update(['card_name' => $cardName, 'thumb_name' => $thumbName]);
+
+        return true;
+    }
+
+    /**
+     * Remove files written by this instance. Call this when the request that
+     * stored them fails and its database changes are rolled back.
+     */
+    public function discardWritten(): void
+    {
+        $disk = Storage::disk(ProductImage::diskName());
+
+        foreach ($this->written as $path) {
+            if ($disk->exists($path)) {
+                $disk->delete($path);
+            }
+        }
+
+        $this->written = [];
+    }
+
+    // ---------------------------------------------------------------------
+    // Internals
+    // ---------------------------------------------------------------------
+
+    protected function put(string $name, string $contents): void
+    {
+        $path = ProductImage::pathFor($name);
+
+        Storage::disk(ProductImage::diskName())->put($path, $contents);
+
+        $this->written[] = $path;
+    }
+
+    protected function lastSortOrder(Product $product): int
+    {
+        return (int) $product->images()->max('sort_order');
+    }
+
+    /**
+     * Run a callback with a higher memory limit, then restore the old one.
+     */
+    protected function withMemoryLimit(callable $callback): mixed
+    {
+        $wanted = config('product_images.memory_limit');
+        $current = ini_get('memory_limit');
+
+        if (! $wanted || $current === '-1' || $this->shorthandToBytes($current) >= $this->shorthandToBytes($wanted)) {
+            return $callback();
+        }
+
+        ini_set('memory_limit', $wanted);
+
+        try {
+            return $callback();
+        } finally {
+            ini_set('memory_limit', $current);
+        }
+    }
+
+    protected function shorthandToBytes(string $value): int
+    {
+        $value = trim($value);
+        $number = (int) $value;
+
+        return match (strtolower(substr($value, -1))) {
+            'g' => $number * 1024 ** 3,
+            'm' => $number * 1024 ** 2,
+            'k' => $number * 1024,
+            default => $number,
+        };
+    }
+}
diff --git a/resources/js/components/custom/ProductImagePicker.vue b/resources/js/components/custom/ProductImagePicker.vue
new file mode 100644
index 0000000..e945681
--- /dev/null
+++ b/resources/js/components/custom/ProductImagePicker.vue
@@ -0,0 +1,179 @@
+<script setup lang="ts">
+import { ChevronLeft, ChevronRight, ImagePlus, X } from '@lucide/vue';
+import { onBeforeUnmount, ref } from 'vue';
+import InputError from '@/components/InputError.vue';
+import { Spinner } from '@/components/ui/spinner';
+import { prepareImageForUpload } from '@/lib/prepareImage';
+import type { ProductImageItem } from '@/types/product';
+
+const props = withDefaults(
+    defineProps<{
+        /** Maximum number of photos (keep in sync with config/product_images.php). */
+        max?: number;
+        /** Largest file the server accepts, in bytes (config/product_images.php: max_upload_kb). */
+        maxBytes?: number;
+    }>(),
+    {
+        max: 5,
+        maxBytes: 10 * 1024 * 1024,
+    },
+);
+
+const emit = defineEmits<{
+    /** A photo that was already saved on the server was taken out. */
+    remove: [item: ProductImageItem];
+}>();
+
+const items = defineModel<ProductImageItem[]>({ required: true });
+
+const preparing = ref(false);
+const message = ref('');
+
+const isFull = () => items.value.length >= props.max;
+
+const handleChange = async (event: Event) => {
+    const input = event.target as HTMLInputElement;
+    const files = Array.from(input.files ?? []).filter((file) => file.type.startsWith('image/'));
+
+    input.value = '';
+    message.value = '';
+
+    if (!files.length) {
+        return;
+    }
+
+    const room = props.max - items.value.length;
+
+    if (files.length > room) {
+        message.value = `A product can have at most ${props.max} images. ${files.length - room} were skipped.`;
+    }
+
+    preparing.value = true;
+
+    try {
+        // One at a time: decoding several big photos together can run a phone out of memory
+        for (const original of files.slice(0, Math.max(room, 0))) {
+            const file = await prepareImageForUpload(original);
+
+            if (file.size > props.maxBytes) {
+                message.value = `${original.name} is too large to upload.`;
+                continue;
+            }
+
+            items.value = [...items.value, { file, preview: URL.createObjectURL(file) }];
+        }
+    } finally {
+        preparing.value = false;
+    }
+};
+
+const move = (index: number, direction: -1 | 1) => {
+    const target = index + direction;
+
+    if (target < 0 || target >= items.value.length) {
+        return;
+    }
+
+    const next = [...items.value];
+    [next[index], next[target]] = [next[target], next[index]];
+    items.value = next;
+};
+
+const remove = (index: number) => {
+    const item = items.value[index];
+
+    if (item.preview.startsWith('blob:')) {
+        URL.revokeObjectURL(item.preview);
+    }
+
+    if (item.id !== undefined) {
+        emit('remove', item);
+    }
+
+    items.value = items.value.filter((_, i) => i !== index);
+};
+
+onBeforeUnmount(() => {
+    items.value.forEach((item) => {
+        if (item.preview.startsWith('blob:')) {
+            URL.revokeObjectURL(item.preview);
+        }
+    });
+});
+</script>
+
+<template>
+    <div>
+        <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
+            <div
+                v-for="(item, index) in items"
+                :key="item.id ?? item.preview"
+                class="relative aspect-square border rounded-xl overflow-hidden bg-white"
+            >
+                <!-- object-contain: show the whole photo, nothing cut off -->
+                <img :src="item.preview" alt="" class="w-full h-full object-contain" />
+
+                <button
+                    type="button"
+                    @click="remove(index)"
+                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1"
+                    title="Remove photo"
+                >
+                    <X class="w-3 h-3" />
+                </button>
+
+                <span
+                    v-if="index === 0"
+                    class="absolute bottom-1 left-1 text-xs bg-black text-white px-1 rounded"
+                >
+                    Primary
+                </span>
+
+                <div class="absolute bottom-1 right-1 flex gap-1">
+                    <button
+                        v-if="index > 0"
+                        type="button"
+                        @click="move(index, -1)"
+                        class="bg-black/60 text-white rounded-full p-1"
+                        title="Move earlier"
+                    >
+                        <ChevronLeft class="w-3 h-3" />
+                    </button>
+                    <button
+                        v-if="index < items.length - 1"
+                        type="button"
+                        @click="move(index, 1)"
+                        class="bg-black/60 text-white rounded-full p-1"
+                        title="Move later"
+                    >
+                        <ChevronRight class="w-3 h-3" />
+                    </button>
+                </div>
+            </div>
+
+            <div
+                v-if="preparing"
+                class="flex flex-col items-center justify-center border rounded-xl aspect-square text-xs text-gray-500"
+            >
+                <Spinner />
+                <span class="mt-1">Preparing…</span>
+            </div>
+
+            <label
+                v-if="!isFull() && !preparing"
+                class="flex flex-col items-center justify-center border-2 border-dashed rounded-xl aspect-square cursor-pointer hover:bg-gray-50"
+            >
+                <ImagePlus class="w-8 h-8 text-gray-400 mb-1" />
+                <span class="text-xs text-gray-500">Add photo</span>
+                <input type="file" multiple accept="image/*" class="hidden" @change="handleChange" />
+            </label>
+        </div>
+
+        <p class="text-xs text-gray-400 mt-2">
+            Up to {{ max }} photos. The first one is the main photo. Photos are shrunk automatically before
+            uploading and are never cropped.
+        </p>
+
+        <InputError :message="message" />
+    </div>
+</template>
diff --git a/resources/js/lib/prepareImage.ts b/resources/js/lib/prepareImage.ts
new file mode 100644
index 0000000..2586674
--- /dev/null
+++ b/resources/js/lib/prepareImage.ts
@@ -0,0 +1,118 @@
+/**
+ * Shrinks a photo in the browser before it is uploaded.
+ *
+ * Phone cameras produce 3-10 MB photos, far more than the shop needs and slow
+ * on mobile data. This scales the photo down so its longest side is at most
+ * `maxSide` pixels and saves it as a JPEG (the server then makes the final
+ * padded, square WebP versions). The photo is rotated upright first, so phone
+ * photos taken sideways are not uploaded sideways.
+ *
+ * If anything goes wrong (very old browser, photo too big to decode, ...) the
+ * original file is returned and the server deals with it.
+ */
+export interface PrepareImageOptions {
+    /** Longest side in pixels after shrinking. */
+    maxSide?: number;
+    /** JPEG quality from 0 to 1. */
+    quality?: number;
+    /** A JPEG or WebP that is already within maxSide and under this many bytes is left alone. */
+    keepIfSmallerThan?: number;
+}
+
+export async function prepareImageForUpload(
+    file: File,
+    options: PrepareImageOptions = {},
+): Promise<File> {
+    const { maxSide = 2000, quality = 0.85, keepIfSmallerThan = 1.5 * 1024 * 1024 } = options;
+
+    if (!file.type.startsWith('image/')) {
+        return file;
+    }
+
+    let source: ImageBitmap | HTMLImageElement | null = null;
+    let objectUrl: string | null = null;
+
+    try {
+        source = await decode(file).catch(async () => {
+            objectUrl = URL.createObjectURL(file);
+
+            return await loadImageElement(objectUrl);
+        });
+
+        const width = 'naturalWidth' in source ? source.naturalWidth : source.width;
+        const height = 'naturalHeight' in source ? source.naturalHeight : source.height;
+
+        if (!width || !height) {
+            return file;
+        }
+
+        const scale = Math.min(1, maxSide / Math.max(width, height));
+        const isWebFriendly = file.type === 'image/jpeg' || file.type === 'image/webp';
+
+        if (scale === 1 && isWebFriendly && file.size <= keepIfSmallerThan) {
+            return file;
+        }
+
+        const canvas = document.createElement('canvas');
+        canvas.width = Math.max(1, Math.round(width * scale));
+        canvas.height = Math.max(1, Math.round(height * scale));
+
+        const context = canvas.getContext('2d');
+
+        if (!context) {
+            return file;
+        }
+
+        // JPEG has no transparency: put transparent PNGs on white
+        context.fillStyle = '#ffffff';
+        context.fillRect(0, 0, canvas.width, canvas.height);
+        context.imageSmoothingQuality = 'high';
+        context.drawImage(source, 0, 0, canvas.width, canvas.height);
+
+        const blob = await new Promise<Blob | null>((resolve) => {
+            canvas.toBlob(resolve, 'image/jpeg', quality);
+        });
+
+        // Never make a photo bigger than it was
+        if (!blob || (scale === 1 && blob.size >= file.size)) {
+            return file;
+        }
+
+        return new File([blob], `${baseName(file.name)}.jpg`, {
+            type: 'image/jpeg',
+            lastModified: Date.now(),
+        });
+    } catch {
+        return file;
+    } finally {
+        if ('close' in (source ?? {})) {
+            (source as ImageBitmap).close();
+        }
+
+        if (objectUrl) {
+            URL.revokeObjectURL(objectUrl);
+        }
+    }
+}
+
+/** Decode with the camera's rotation (EXIF) already applied. */
+function decode(file: File): Promise<ImageBitmap> {
+    if (typeof createImageBitmap !== 'function') {
+        return Promise.reject(new Error('createImageBitmap is not supported'));
+    }
+
+    return createImageBitmap(file, { imageOrientation: 'from-image' });
+}
+
+function loadImageElement(url: string): Promise<HTMLImageElement> {
+    return new Promise((resolve, reject) => {
+        const image = new Image();
+        image.onload = () => resolve(image);
+        image.onerror = () => reject(new Error('The image could not be read'));
+        image.src = url;
+    });
+}
+
+function baseName(filename: string): string {
+    return filename.replace(/\.[^.]+$/, '') || 'photo';
+}
diff --git a/resources/js/pages/app/orders/orders/Create.vue b/resources/js/pages/app/orders/orders/Create.vue
index abc81cf..59c0fdc 100644
--- a/resources/js/pages/app/orders/orders/Create.vue
+++ b/resources/js/pages/app/orders/orders/Create.vue
@@ -299,8 +299,8 @@ onUnmounted(() => {
                     :key="product.id" 
                     class="bg-background text-foreground p-3 rounded-lg shadow-sm border hover:shadow-md transition"
                 >
-                    <div class="w-full h-32 overflow-hidden rounded mb-2 bg-gray-100">
-                        <img :src="product.thumbnail_url" :alt="product.name" class="w-full h-full object-cover" />
+                    <div class="w-full h-32 overflow-hidden rounded mb-2 bg-white">
+                        <img :src="product.thumbnail_url" :alt="product.name" class="w-full h-full object-contain" />
                     </div>
 
                     <div class="space-y-1">
diff --git a/resources/js/pages/app/products/products/Create.vue b/resources/js/pages/app/products/products/Create.vue
index fd82676..898064f 100644
--- a/resources/js/pages/app/products/products/Create.vue
+++ b/resources/js/pages/app/products/products/Create.vue
@@ -1,8 +1,8 @@
 <script setup lang="ts">
 import { Link, Head, useForm } from '@inertiajs/vue3';
-import { ImagePlus, X } from '@lucide/vue';
 import { ref } from 'vue';
 import FormHeader from '@/components/custom/FormHeader.vue';
+import ProductImagePicker from '@/components/custom/ProductImagePicker.vue';
 import InputError from '@/components/InputError.vue';
 import { Button } from '@/components/ui/button';
 import { Input } from '@/components/ui/input';
@@ -11,6 +11,7 @@ import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectVa
 import { Spinner } from '@/components/ui/spinner';
 import { Textarea } from '@/components/ui/textarea';
 import productRoutes from '@/routes/products';
+import type { ProductImageItem } from '@/types/product';
 
 interface Category {
     id: number;
@@ -45,72 +46,11 @@ const form = useForm({
     images: [] as File[],
 });
 
-const imagePreviews = ref<string[]>([]);
-
-const MAX_IMAGES = 5;
-const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
-const MAX_TOTAL_SIZE = 10 * 1024 * 1024; // 10MB
-
-const isMaxImagesReached = () => form.images.length >= MAX_IMAGES;
-
-const handleImageChange = (e: Event) => {
-    const target = e.target as HTMLInputElement;
-    const files = Array.from(target.files || []);
-
-    if (!files.length) {
-        return;
-    }
-
-    // Max count
-    if (form.images.length + files.length > MAX_IMAGES) {
-        alert(`Maximum ${MAX_IMAGES} images allowed.`);
-        target.value = '';
-
-        return;
-    }
-
-    // Per-file size
-    for (const file of files) {
-        if (file.size > MAX_FILE_SIZE) {
-            alert(`${file.name} exceeds 2MB`);
-            target.value = '';
-
-            return;
-        }
-    }
-
-    // Total size
-    const currentSize = form.images.reduce((sum, f) => sum + f.size, 0);
-    const newSize = files.reduce((sum, f) => sum + f.size, 0);
-
-    if (currentSize + newSize > MAX_TOTAL_SIZE) {
-        alert(`Total size cannot exceed 10MB`);
-        target.value = '';
-
-        return;
-    }
-
-    // Add files + previews
-    files.forEach(file => {
-        if (!file.type.startsWith('image/')) {
-            return;
-        }
-
-        form.images.push(file);
-        imagePreviews.value.push(URL.createObjectURL(file));
-    });
-
-    target.value = '';
-};
-
-const removeImage = (index: number) => {
-    URL.revokeObjectURL(imagePreviews.value[index]);
-
-    form.images.splice(index, 1);
-    imagePreviews.value.splice(index, 1);
-};
+const imageItems = ref<ProductImageItem[]>([]);
 
 const submitForm = () => {
+    form.images = imageItems.value.filter((item) => item.file).map((item) => item.file as File);
+
     form.post(productRoutes.store.url(), {
         preserveScroll: true,
     });
@@ -338,49 +278,9 @@ const submitForm = () => {
 
             <div class="form-section">
                 <h3 class="section-title">Product Images</h3>
-                
-                <div class="inputs-group">
-                    <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
-                        
-                        <!-- Existing Images -->
-                        <div 
-                            v-for="(preview, idx) in imagePreviews" 
-                            :key="idx" 
-                            class="relative w-full aspect-square rounded-xl overflow-hidden border"
-                        >
-                            <img :src="preview" class="w-full h-full object-cover" />
-
-                            <button 
-                                type="button" 
-                                @click="removeImage(idx)"
-                                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full"
-                            >
-                                <X class="w-3 h-3" />
-                            </button>
-                        </div>
-
-                        <!-- Upload Button (acts like a tile) -->
-                        <label
-                            v-if="!isMaxImagesReached()"
-                            class="flex flex-col items-center justify-center border-2 border-dashed rounded-xl aspect-square cursor-pointer hover:bg-gray-50"
-                        >
-                            <ImagePlus class="w-8 h-8 text-gray-400 mb-1" />
-                            <span class="text-xs text-gray-500">Upload</span>
-
-                            <input
-                                type="file"
-                                multiple
-                                accept="image/*"
-                                class="hidden"
-                                @change="handleImageChange"
-                            />
-                        </label>
 
-                    </div>
-
-                    <p class="text-xs text-gray-400 mt-2">
-                        Upload up to 5 images. First image will be primary.
-                    </p>
+                <div class="inputs-group">
+                    <ProductImagePicker v-model="imageItems" />
 
                     <InputError :message="form.errors.images" />
                 </div>
diff --git a/resources/js/pages/app/products/products/Edit.vue b/resources/js/pages/app/products/products/Edit.vue
index e99a6e1..62d3733 100644
--- a/resources/js/pages/app/products/products/Edit.vue
+++ b/resources/js/pages/app/products/products/Edit.vue
@@ -1,8 +1,8 @@
 <script setup lang="ts">
 import { Link, Head, useForm } from '@inertiajs/vue3';
-import { ImagePlus, X } from '@lucide/vue';
 import { ref } from 'vue';
 import FormHeader from '@/components/custom/FormHeader.vue';
+import ProductImagePicker from '@/components/custom/ProductImagePicker.vue';
 import InputError from '@/components/InputError.vue';
 import { Button } from '@/components/ui/button';
 import { Input } from '@/components/ui/input';
@@ -11,6 +11,7 @@ import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectVa
 import { Spinner } from '@/components/ui/spinner';
 import { Textarea } from '@/components/ui/textarea';
 import productRoutes from '@/routes/products';
+import type { ProductImageItem } from '@/types/product';
 
 interface Category {
     id: number;
@@ -26,6 +27,7 @@ interface ProductImage {
     id: number;
     name: string;
     image_url: string;
+    card_url: string;
 }
 
 interface Product {
@@ -54,12 +56,6 @@ const props = defineProps<{
     product: Product;
 }>();
 
-type ImageItem = {
-    id?: number;
-    file?: File;
-    preview: string;
-};
-
 const form = useForm({
     name: props.product.name,
     description: props.product.description || '',
@@ -77,77 +73,31 @@ const form = useForm({
     product_brand_id: props.product.product_brand_id,
     images: [] as File[],
     images_to_delete: [] as number[],
+    image_order: [] as string[],
     _method: 'PUT',
 });
 
-const MAX_IMAGES = 5;
-const MAX_FILE_SIZE = 2 * 1024 * 1024;
-
-const images = ref<ImageItem[]>(
-    props.product.images.map(img => ({
+const images = ref<ProductImageItem[]>(
+    props.product.images.map((img) => ({
         id: img.id,
-        preview: img.image_url,
-    }))
+        preview: img.card_url ?? img.image_url,
+    })),
 );
 
-const isMaxImagesReached = () => images.value.length >= MAX_IMAGES;
-
-const handleImageChange = (e: Event) => {
-    const target = e.target as HTMLInputElement;
-    const files = Array.from(target.files || []);
-
-    if (!files.length) {
-        return;
-    }
-
-    if (images.value.length + files.length > MAX_IMAGES) {
-        alert(`Max ${MAX_IMAGES} images allowed`);
-        target.value = '';
-
-        return;
-    }
-
-    for (const file of files) {
-        if (file.size > MAX_FILE_SIZE) {
-            alert(`${file.name} exceeds 2MB`);
-            target.value = '';
-
-            return;
-        }
-    }
-
-    files.forEach(file => {
-        if (!file.type.startsWith('image/')) {
-            return;
-        }
-
-        images.value.push({
-            file,
-            preview: URL.createObjectURL(file),
-        });
-    });
-
-    target.value = '';
-};
-
-const removeImage = (index: number) => {
-    const item = images.value[index];
-
-    if (item.id) {
+// A photo that was already saved was taken out: tell the server to delete it
+const onImageRemoved = (item: ProductImageItem) => {
+    if (item.id !== undefined) {
         form.images_to_delete.push(item.id);
     }
-
-    if (item.preview.startsWith('blob:')) {
-        URL.revokeObjectURL(item.preview);
-    }
-
-    images.value.splice(index, 1);
 };
 
 const submitForm = () => {
-    form.images = images.value
-        .filter(i => i.file)
-        .map(i => i.file as File);
+    // New files go in `images`; `image_order` lists every photo in the order shown on screen:
+    // an existing photo by id, a new one as "new-<position in images>".
+    form.images = images.value.filter((item) => item.file).map((item) => item.file as File);
+
+    let newIndex = 0;
+    form.image_order = images.value.map((item) => (item.id !== undefined ? String(item.id) : `new-${newIndex++}`));
 
     form.post(
         productRoutes.update({
@@ -387,37 +337,7 @@ const submitForm = () => {
             <div class="form-section">
                 <h3>Product Images</h3>
 
-                <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
-
-                    <div
-                        v-for="(item, index) in images"
-                        :key="item.id ?? item.preview"
-                        class="relative aspect-square border rounded-xl overflow-hidden"
-                    >
-                        <img :src="item.preview" class="w-full h-full object-cover" />
-
-                        <button
-                            type="button"
-                            @click="removeImage(index)"
-                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1"
-                        >
-                            <X class="w-3 h-3" />
-                        </button>
-
-                        <span v-if="index === 0" class="absolute bottom-1 left-1 text-xs bg-black text-white px-1 rounded">
-                            Primary
-                        </span>
-                    </div>
-
-                    <label
-                        v-if="!isMaxImagesReached()"
-                        class="flex flex-col items-center justify-center border-2 border-dashed rounded-xl aspect-square cursor-pointer"
-                    >
-                        <ImagePlus class="w-6 h-6 text-gray-400" />
-                        <input type="file" multiple accept="image/*" class="hidden" @change="handleImageChange" />
-                    </label>
-
-                </div>
+                <ProductImagePicker v-model="images" @remove="onImageRemoved" />
 
                 <InputError :message="form.errors.images" />
             </div>
diff --git a/resources/js/pages/storefront/components/ProductCard.vue b/resources/js/pages/storefront/components/ProductCard.vue
index fef6d1c..67bef3c 100644
--- a/resources/js/pages/storefront/components/ProductCard.vue
+++ b/resources/js/pages/storefront/components/ProductCard.vue
@@ -84,11 +84,11 @@ onUnmounted(() => {
          @mouseleave="startAutoSlide">
         
         <!-- Image Carousel -->
-        <div class="relative overflow-hidden bg-gray-100 aspect-square h-90 w-full">
+        <div class="relative overflow-hidden bg-white aspect-square h-90 w-full">
             <img 
-                :src="currentImage?.url || '/assets/images/default-image.png'" 
+                :src="currentImage?.card_url || currentImage?.url || '/assets/images/default-image.png'" 
                 :alt="currentImage?.alt || product.slug"
-                class="w-full h-full object-cover transition-transform duration-500"
+                class="w-full h-full object-contain transition-transform duration-500"
                 :class="{ 'scale-105': isHovered }"
                 @mouseenter="isHovered = true"
                 @mouseleave="isHovered = false"
diff --git a/resources/js/pages/storefront/components/ProductCardSingleImage.vue b/resources/js/pages/storefront/components/ProductCardSingleImage.vue
index 38ac683..1bbd1a7 100644
--- a/resources/js/pages/storefront/components/ProductCardSingleImage.vue
+++ b/resources/js/pages/storefront/components/ProductCardSingleImage.vue
@@ -19,6 +19,7 @@ interface Product {
     is_new: boolean;
     is_active: boolean;
     thumbnail_url: string;
+    card_image_url?: string;
 }
 
 defineProps<{
@@ -32,11 +33,11 @@ const isHovered = ref(false);
     <div class="bg-white shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 group">
         
         <!-- Image Carousel -->
-        <div class="relative overflow-hidden bg-gray-100 aspect-square h-70 w-full">
+        <div class="relative overflow-hidden bg-white aspect-square h-70 w-full">
             <img 
-                :src="product.thumbnail_url" 
+                :src="product.card_image_url || product.thumbnail_url" 
                 :alt="product.slug"
-                class="w-full h-full object-cover transition-transform duration-500"
+                class="w-full h-full object-contain transition-transform duration-500"
                 :class="{ 'scale-105': isHovered }"
                 @mouseenter="isHovered = true"
                 @mouseleave="isHovered = false"
diff --git a/resources/js/pages/storefront/products/details/Index.vue b/resources/js/pages/storefront/products/details/Index.vue
index 17cae32..4c1fabc 100644
--- a/resources/js/pages/storefront/products/details/Index.vue
+++ b/resources/js/pages/storefront/products/details/Index.vue
@@ -63,7 +63,7 @@ const selectImage = (index: number) => {
                         :class="selectedImageIndex === index ? 'border-blue-800' : 'border-transparent'"
                         @click="selectImage(index)"
                     >
-                        <img :src="image.url" :alt="image.alt || `${product.data.name} thumbnail ${index + 1}`" class="w-full h-full object-cover" />
+                        <img :src="image.thumb_url || image.url" :alt="image.alt || `${product.data.name} thumbnail ${index + 1}`" class="w-full h-full object-contain" />
                     </div>
                 </div>
             </div>
diff --git a/resources/js/types/product.ts b/resources/js/types/product.ts
index 8bb02b6..c18a7c9 100644
--- a/resources/js/types/product.ts
+++ b/resources/js/types/product.ts
@@ -1,8 +1,20 @@
 export interface ProductImage {
+    /** Full size (product page). */
     url: string;
+    /** Medium size (storefront grid and carousel). */
+    card_url?: string;
+    /** Small size (thumbnails). */
+    thumb_url?: string;
     alt: string;
 }
 
+/** One photo in the admin image picker: either already saved (id) or newly chosen (file). */
+export interface ProductImageItem {
+    id?: number;
+    file?: File;
+    preview: string;
+}
+
 export interface Product {
     id: number;
     uuid: string;
@@ -20,6 +32,7 @@ export interface Product {
     category: string;
     tags: string[];
     thumbnail_url: string;
+    card_image_url?: string;
     images: ProductImage[];
     rating: number;
     reviews_count: number;
diff --git a/tests/Concerns/CreatesTestImages.php b/tests/Concerns/CreatesTestImages.php
new file mode 100644
index 0000000..8e7be87
--- /dev/null
+++ b/tests/Concerns/CreatesTestImages.php
@@ -0,0 +1,75 @@
+<?php
+
+namespace Tests\Concerns;
+
+trait CreatesTestImages
+{
+    /**
+     * A JPEG whose left half is red and right half is blue.
+     */
+    protected function twoToneJpeg(int $width, int $height): string
+    {
+        $image = imagecreatetruecolor($width, $height);
+        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 255, 0, 0));
+        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));
+
+        ob_start();
+        imagejpeg($image, null, 95);
+
+        return ob_get_clean();
+    }
+
+    /**
+     * Add an EXIF "orientation" tag to a JPEG (6 = the camera was held sideways).
+     */
+    protected function withExifOrientation(string $jpeg, int $orientation): string
+    {
+        $tiff = "II*\x00\x08\x00\x00\x00"
+            .pack('v', 1)
+            .pack('vvVV', 0x0112, 3, 1, $orientation)
+            .pack('V', 0);
+
+        $payload = "Exif\x00\x00".$tiff;
+        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
+
+        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
+    }
+
+    /**
+     * @return array{0: int, 1: int} width and height
+     */
+    protected function sizeOf(string $bytes): array
+    {
+        $info = getimagesizefromstring($bytes);
+
+        return [$info[0], $info[1]];
+    }
+
+    /**
+     * @return array{0: int, 1: int, 2: int} red, green, blue at a pixel
+     */
+    protected function pixel(string $bytes, int $x, int $y): array
+    {
+        $image = imagecreatefromstring($bytes);
+        $rgb = imagecolorat($image, $x, $y);
+
+        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
+    }
+
+    protected function assertIsWhite(array $rgb): void
+    {
+        $this->assertGreaterThan(240, min($rgb), 'Expected a white pixel, got '.json_encode($rgb));
+    }
+
+    protected function assertIsRed(array $rgb): void
+    {
+        $this->assertGreaterThan(200, $rgb[0], 'Expected a red pixel, got '.json_encode($rgb));
+        $this->assertLessThan(80, $rgb[2], 'Expected a red pixel, got '.json_encode($rgb));
+    }
+
+    protected function assertIsBlue(array $rgb): void
+    {
+        $this->assertGreaterThan(200, $rgb[2], 'Expected a blue pixel, got '.json_encode($rgb));
+        $this->assertLessThan(80, $rgb[0], 'Expected a blue pixel, got '.json_encode($rgb));
+    }
+}
diff --git a/tests/Feature/Product/ProductImageServiceTest.php b/tests/Feature/Product/ProductImageServiceTest.php
new file mode 100644
index 0000000..adbc8ab
--- /dev/null
+++ b/tests/Feature/Product/ProductImageServiceTest.php
@@ -0,0 +1,247 @@
+<?php
+
+namespace Tests\Feature\Product;
+
+use Illuminate\Foundation\Testing\RefreshDatabase;
+use Illuminate\Support\Facades\Storage;
+use Illuminate\Validation\ValidationException;
+use InvalidArgumentException;
+use Modules\Product\Models\Product;
+use Modules\Product\Models\ProductImage;
+use Modules\Product\Services\ProductImageService;
+use Tests\Concerns\CreatesTestImages;
+use Tests\TestCase;
+
+class ProductImageServiceTest extends TestCase
+{
+    use CreatesTestImages;
+    use RefreshDatabase;
+
+    protected ProductImageService $service;
+
+    protected function setUp(): void
+    {
+        parent::setUp();
+
+        Storage::fake('public');
+        $this->service = new ProductImageService;
+    }
+
+    protected function product(string $name = 'Nike Air Force'): Product
+    {
+        return Product::create(['name' => $name, 'price' => 1500]);
+    }
+
+    protected function read(string $name): string
+    {
+        return Storage::disk('public')->get(ProductImage::pathFor($name));
+    }
+
+    public function test_a_wide_photo_is_padded_to_a_square_and_never_cropped(): void
+    {
+        $image = $this->service->storeBytes($this->product(), $this->twoToneJpeg(1000, 400));
+
+        $full = $this->read($image->name);
+
+        // Square canvas, as wide as the original photo (no enlarging, no cropping)
+        $this->assertSame([1000, 1000], $this->sizeOf($full));
+
+        // Padding above and below, the whole photo in the middle
+        $this->assertIsWhite($this->pixel($full, 500, 50));
+        $this->assertIsWhite($this->pixel($full, 500, 950));
+        $this->assertIsRed($this->pixel($full, 5, 500));    // far left edge is still there
+        $this->assertIsBlue($this->pixel($full, 995, 500)); // far right edge is still there
+    }
+
+    public function test_a_tall_photo_is_padded_on_the_sides(): void
+    {
+        $image = $this->service->storeBytes($this->product(), $this->twoToneJpeg(400, 1000));
+
+        $full = $this->read($image->name);
+
+        $this->assertSame([1000, 1000], $this->sizeOf($full));
+        $this->assertIsWhite($this->pixel($full, 50, 500));
+        $this->assertIsWhite($this->pixel($full, 950, 500));
+    }
+
+    public function test_a_large_photo_gets_three_sizes(): void
+    {
+        $image = $this->service->storeBytes($this->product(), $this->twoToneJpeg(2400, 1600));
+
+        $this->assertSame([1200, 1200], $this->sizeOf($this->read($image->name)));
+        $this->assertSame([600, 600], $this->sizeOf($this->read($image->card_name)));
+        $this->assertSame([300, 300], $this->sizeOf($this->read($image->thumb_name)));
+
+        foreach ([$image->name, $image->card_name, $image->thumb_name] as $name) {
+            $this->assertSame('image/webp', getimagesizefromstring($this->read($name))['mime']);
+        }
+    }
+
+    public function test_a_small_photo_is_never_enlarged(): void
+    {
+        $image = $this->service->storeBytes($this->product(), $this->twoToneJpeg(300, 200));
+
+        $this->assertSame([300, 300], $this->sizeOf($this->read($image->name)));
+        $this->assertSame([300, 300], $this->sizeOf($this->read($image->card_name)));
+        $this->assertSame([300, 300], $this->sizeOf($this->read($image->thumb_name)));
+    }
+
+    public function test_a_sideways_phone_photo_is_rotated_upright(): void
+    {
+        if (! function_exists('exif_read_data')) {
+            $this->markTestSkipped('The exif PHP extension is not available.');
+        }
+
+        // Stored as 400x200 (red left, blue right) with "rotate 90 degrees clockwise" in EXIF.
+        $jpeg = $this->withExifOrientation($this->twoToneJpeg(400, 200), 6);
+
+        $image = $this->service->storeBytes($this->product(), $jpeg);
+        $full = $this->read($image->name);
+
+        // Upright it is 200 wide and 400 tall, red on top and blue at the bottom,
+        // centred on a 400x400 canvas.
+        $this->assertSame([400, 400], $this->sizeOf($full));
+        $this->assertIsRed($this->pixel($full, 200, 50));
+        $this->assertIsBlue($this->pixel($full, 200, 350));
+        $this->assertIsWhite($this->pixel($full, 20, 200));
+    }
+
+    public function test_files_are_named_without_the_product_name(): void
+    {
+        $product = $this->product('Nike Air Force');
+        $image = $this->service->storeBytes($product, $this->twoToneJpeg(500, 500));
+
+        $this->assertMatchesRegularExpression('#^'.$product->id.'/[0-9a-f-]{36}\.webp$#', $image->name);
+        $this->assertStringNotContainsString('nike', $image->name);
+        $this->assertStringEndsWith('_card.webp', $image->card_name);
+        $this->assertStringEndsWith('_thumb.webp', $image->thumb_name);
+    }
+
+    public function test_something_that_is_not_an_image_is_rejected(): void
+    {
+        $this->expectException(InvalidArgumentException::class);
+
+        $this->service->storeBytes($this->product(), 'definitely not an image');
+    }
+
+    public function test_an_absurdly_large_photo_is_rejected_before_it_is_decoded(): void
+    {
+        $image = imagecreatetruecolor(6001, 10);
+        ob_start();
+        imagepng($image);
+        $png = ob_get_clean();
+
+        $this->expectException(InvalidArgumentException::class);
+        $this->expectExceptionMessage('too large');
+
+        $this->service->storeBytes($this->product(), $png);
+    }
+
+    public function test_a_product_cannot_have_more_than_the_maximum_number_of_images(): void
+    {
+        $product = $this->product();
+
+        for ($i = 0; $i < 5; $i++) {
+            $this->service->storeBytes($product, $this->twoToneJpeg(200, 200));
+        }
+
+        $this->expectException(ValidationException::class);
+
+        $this->service->ensureRoom($product, 1);
+    }
+
+    public function test_deleting_an_image_removes_all_its_files_only_after_commit(): void
+    {
+        $product = $this->product();
+        $image = $this->service->storeBytes($product, $this->twoToneJpeg(500, 500));
+        $paths = $image->paths();
+
+        $this->assertCount(3, $paths);
+
+        $this->service->deleteForProduct($product, [$image->id]);
+
+        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
+
+        foreach ($paths as $path) {
+            Storage::disk('public')->assertMissing($path);
+        }
+    }
+
+    public function test_apply_order_follows_the_requested_order_and_keeps_unlisted_images_last(): void
+    {
+        $product = $this->product();
+        $a = $this->service->storeBytes($product, $this->twoToneJpeg(200, 200));
+        $b = $this->service->storeBytes($product, $this->twoToneJpeg(200, 200));
+        $c = $this->service->storeBytes($product, $this->twoToneJpeg(200, 200));
+
+        $this->service->applyOrder($product, [(string) $c->id, (string) $a->id]);
+
+        $this->assertSame([$c->id, $a->id, $b->id], $product->images()->pluck('id')->all());
+    }
+
+    public function test_apply_order_understands_new_uploads_and_ignores_other_products_images(): void
+    {
+        $product = $this->product();
+        $other = $this->product('Other product');
+
+        $existing = $this->service->storeBytes($product, $this->twoToneJpeg(200, 200));
+        $foreign = $this->service->storeBytes($other, $this->twoToneJpeg(200, 200));
+        $new = $this->service->storeBytes($product, $this->twoToneJpeg(200, 200));
+
+        $this->service->applyOrder($product, ['new-0', (string) $foreign->id, (string) $existing->id], [0 => $new]);
+
+        $this->assertSame([$new->id, $existing->id], $product->images()->pluck('id')->all());
+        $this->assertSame(1, $foreign->fresh()->sort_order);
+    }
+
+    public function test_images_from_before_variants_existed_fall_back_to_the_full_file(): void
+    {
+        $product = $this->product();
+        $legacy = $product->images()->create(['name' => 'old-shoe_1_0_20260101_000000_abc123.webp', 'sort_order' => 1]);
+
+        $this->assertStringEndsWith('/storage/products/old-shoe_1_0_20260101_000000_abc123.webp', $legacy->image_url);
+        $this->assertSame($legacy->image_url, $legacy->card_url);
+        $this->assertSame($legacy->image_url, $legacy->thumbnail_url);
+    }
+
+    public function test_regenerate_adds_card_and_thumb_to_an_old_image_without_touching_the_full_file(): void
+    {
+        $product = $this->product();
+
+        // An old-style photo: 800x800 WebP with the slug in its name, no variants
+        $fullBytes = $this->service->makeVariants($this->twoToneJpeg(800, 800), ['thumb'])['thumb'];
+        Storage::disk('public')->put('products/old-shoe_1_0_20260101_000000_abc123.webp', $fullBytes);
+        $legacy = $product->images()->create(['name' => 'old-shoe_1_0_20260101_000000_abc123.webp', 'sort_order' => 1]);
+
+        $this->assertTrue($this->service->regenerate($legacy));
+
+        $legacy->refresh();
+        $this->assertSame('old-shoe_1_0_20260101_000000_abc123.webp', $legacy->name);
+        $this->assertSame('old-shoe_1_0_20260101_000000_abc123_card.webp', $legacy->card_name);
+        $this->assertSame('old-shoe_1_0_20260101_000000_abc123_thumb.webp', $legacy->thumb_name);
+        Storage::disk('public')->assertExists('products/'.$legacy->card_name);
+        Storage::disk('public')->assertExists('products/'.$legacy->thumb_name);
+
+        // Nothing left to do the second time
+        $this->assertFalse($this->service->regenerate($legacy));
+    }
+
+    public function test_regenerate_skips_images_whose_file_is_missing(): void
+    {
+        $legacy = $this->product()->images()->create(['name' => 'gone.webp', 'sort_order' => 1]);
+
+        $this->assertFalse($this->service->regenerate($legacy));
+        $this->assertNull($legacy->fresh()->card_name);
+    }
+
+    public function test_discard_written_removes_files_from_a_failed_request(): void
+    {
+        $image = $this->service->storeBytes($this->product(), $this->twoToneJpeg(500, 500));
+
+        $this->service->discardWritten();
+
+        foreach ($image->paths() as $path) {
+            Storage::disk('public')->assertMissing($path);
+        }
+    }
+}
diff --git a/tests/Feature/Product/ProductImageUploadTest.php b/tests/Feature/Product/ProductImageUploadTest.php
new file mode 100644
index 0000000..da788b8
--- /dev/null
+++ b/tests/Feature/Product/ProductImageUploadTest.php
@@ -0,0 +1,218 @@
+<?php
+
+namespace Tests\Feature\Product;
+
+use App\Models\User;
+use Illuminate\Foundation\Testing\RefreshDatabase;
+use Illuminate\Http\UploadedFile;
+use Illuminate\Support\Facades\DB;
+use Illuminate\Support\Facades\Storage;
+use Modules\Product\Models\Product;
+use Modules\Product\Services\ProductImageService;
+use Modules\User\Enums\UserRoles;
+use Tests\Concerns\CreatesTestImages;
+use Tests\TestCase;
+
+class ProductImageUploadTest extends TestCase
+{
+    use CreatesTestImages;
+    use RefreshDatabase;
+
+    protected function setUp(): void
+    {
+        parent::setUp();
+
+        Storage::fake('public');
+
+        $this->actingAs(User::factory()->create(['role' => UserRoles::ADMIN]));
+    }
+
+    protected function payload(array $extra = []): array
+    {
+        return array_merge([
+            'name' => 'Adidas Samba',
+            'price' => 2500,
+            'is_active' => true,
+        ], $extra);
+    }
+
+    protected function photo(string $name = 'shoe.jpg', int $width = 3000, int $height = 2000): UploadedFile
+    {
+        return UploadedFile::fake()->image($name, $width, $height);
+    }
+
+    public function test_creating_a_product_stores_every_photo_in_three_sizes(): void
+    {
+        $this->post(route('products.store'), $this->payload([
+            'images' => [$this->photo('a.jpg'), $this->photo('b.png')],
+        ]))->assertSessionHasNoErrors();
+
+        $product = Product::firstWhere('name', 'Adidas Samba');
+        $images = $product->images;
+
+        $this->assertCount(2, $images);
+        $this->assertSame([1, 2], $images->pluck('sort_order')->all());
+
+        foreach ($images as $image) {
+            $this->assertCount(3, $image->paths());
+
+            foreach ($image->paths() as $path) {
+                Storage::disk('public')->assertExists($path);
+            }
+        }
+    }
+
+    public function test_a_phone_sized_photo_above_the_old_2mb_limit_is_accepted(): void
+    {
+        $this->post(route('products.store'), $this->payload([
+            'images' => [$this->photo('big.jpg')->size(6000)], // ~6 MB
+        ]))->assertSessionHasNoErrors();
+
+        $this->assertSame(1, Product::firstWhere('name', 'Adidas Samba')->images()->count());
+    }
+
+    public function test_photos_over_the_new_limit_and_non_images_are_rejected(): void
+    {
+        $this->post(route('products.store'), $this->payload([
+            'images' => [$this->photo('huge.jpg')->size(11000)],
+        ]))->assertSessionHasErrors('images.0');
+
+        $this->post(route('products.store'), $this->payload([
+            'images' => [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
+        ]))->assertSessionHasErrors('images.0');
+
+        $this->post(route('products.store'), $this->payload([
+            'images' => [$this->photo('wide.jpg', 6500, 100)],
+        ]))->assertSessionHasErrors('images.0');
+
+        $this->assertDatabaseCount('products', 0);
+    }
+
+    public function test_more_than_five_photos_are_rejected(): void
+    {
+        $this->post(route('products.store'), $this->payload([
+            'images' => array_map(fn ($i) => $this->photo("p{$i}.jpg", 400, 300), range(1, 6)),
+        ]))->assertSessionHasErrors('images');
+    }
+
+    public function test_editing_adds_removes_and_reorders_photos_in_one_save(): void
+    {
+        $service = new ProductImageService;
+        $product = Product::create(['name' => 'Adidas Samba', 'price' => 2500]);
+        $first = $service->storeBytes($product, $this->twoToneJpeg(500, 500));
+        $second = $service->storeBytes($product, $this->twoToneJpeg(500, 500));
+        $third = $service->storeBytes($product, $this->twoToneJpeg(500, 500));
+
+        $this->put(route('products.update', $product), $this->payload([
+            'images' => [$this->photo('new.jpg', 800, 600)],
+            'images_to_delete' => [$second->id],
+            // new photo first, then the old third, then the old first
+            'image_order' => ['new-0', (string) $third->id, (string) $first->id],
+        ]))->assertSessionHasNoErrors();
+
+        $images = $product->images()->get();
+
+        $this->assertCount(3, $images);
+        $this->assertSame($third->id, $images[1]->id);
+        $this->assertSame($first->id, $images[2]->id);
+        $this->assertNotContains($second->id, $images->pluck('id')->all());
+
+        // The photo that was removed took its files with it
+        foreach ($second->paths() as $path) {
+            Storage::disk('public')->assertMissing($path);
+        }
+
+        // The ones that stayed (and the new one) still have theirs
+        foreach ($images as $image) {
+            foreach ($image->paths() as $path) {
+                Storage::disk('public')->assertExists($path);
+            }
+        }
+    }
+
+    public function test_editing_cannot_push_a_product_over_the_photo_limit(): void
+    {
+        $service = new ProductImageService;
+        $product = Product::create(['name' => 'Adidas Samba', 'price' => 2500]);
+
+        for ($i = 0; $i < 4; $i++) {
+            $service->storeBytes($product, $this->twoToneJpeg(300, 300));
+        }
+
+        $filesBefore = count(Storage::disk('public')->allFiles());
+
+        $this->put(route('products.update', $product), $this->payload([
+            'images' => [$this->photo('a.jpg', 400, 300), $this->photo('b.jpg', 400, 300)],
+        ]))->assertSessionHasErrors('images');
+
+        $this->assertSame(4, $product->images()->count());
+        // ...and the failed request left no stray files behind
+        $this->assertCount($filesBefore, Storage::disk('public')->allFiles());
+    }
+
+    public function test_renaming_a_product_does_not_touch_its_photo_files(): void
+    {
+        $service = new ProductImageService;
+        $product = Product::create(['name' => 'Adidas Samba', 'price' => 2500]);
+        $image = $service->storeBytes($product, $this->twoToneJpeg(500, 500));
+        $namesBefore = [$image->name, $image->card_name, $image->thumb_name];
+
+        $this->put(route('products.update', $product), $this->payload(['name' => 'Adidas Gazelle']))
+            ->assertSessionHasNoErrors();
+
+        $this->assertSame('adidas-gazelle', $product->fresh()->slug);
+
+        $image->refresh();
+        $this->assertSame($namesBefore, [$image->name, $image->card_name, $image->thumb_name]);
+
+        foreach ($image->paths() as $path) {
+            Storage::disk('public')->assertExists($path);
+        }
+    }
+
+    public function test_deleting_a_product_removes_its_photo_files_and_folder(): void
+    {
+        $service = new ProductImageService;
+        $product = Product::create(['name' => 'Adidas Samba', 'price' => 2500]);
+        $image = $service->storeBytes($product, $this->twoToneJpeg(500, 500));
+        $paths = $image->paths();
+
+        $this->delete(route('products.destroy', $product))->assertRedirect();
+
+        $this->assertDatabaseMissing('products', ['id' => $product->id]);
+
+        foreach ($paths as $path) {
+            Storage::disk('public')->assertMissing($path);
+        }
+
+        Storage::disk('public')->assertMissing('products/'.$product->id);
+    }
+
+    public function test_the_products_list_does_not_run_extra_queries_per_product(): void
+    {
+        $service = new ProductImageService;
+
+        $make = function (int $count) use ($service) {
+            for ($i = 0; $i < $count; $i++) {
+                $product = Product::create(['name' => 'Shoe '.uniqid(), 'price' => 1000]);
+                $service->storeBytes($product, $this->twoToneJpeg(200, 200));
+            }
+        };
+
+        $countQueries = function () {
+            DB::flushQueryLog();
+            DB::enableQueryLog();
+            $this->get(route('products.index'))->assertOk();
+
+            return count(DB::getQueryLog());
+        };
+
+        $make(2);
+        $few = $countQueries();
+
+        $make(8);
+        $many = $countQueries();
+
+        $this->assertSame($few, $many, 'The number of queries grows with the number of products.');
+    }
+}