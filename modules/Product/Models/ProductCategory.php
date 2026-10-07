<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Modules\Support\Concerns\HasUuid;
use Modules\Support\Concerns\HasSlug;

class ProductCategory extends Model
{
    use HasUuid, HasSlug;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    protected static function booted()
    {
        static::updating(function ($product_category) {
            if ($product_category->isDirty('name')) {
                $old_name = $product_category->getOriginal('name');
                $new_name = $product_category->name;
                
                // Update slug
                $product_category->slug = Str::slug($new_name);
                
                // Rename image
                $old_image = $product_category->getOriginal('image');
                $new_slug = Str::slug($new_name);
                
                if ($old_image) {
                    $old_slug = Str::slug($old_name);
                    $new_filename = str_replace($old_slug, $new_slug, $old_image);
                    
                    // Only rename if the filename actually changed
                    if ($old_image !== $new_filename) {
                        $old_path = 'product-categories/' . $old_image;
                        $new_path = 'product-categories/' . $new_filename;
                        
                        // Rename the actual file
                        if (Storage::disk('public')->exists($old_path)) {
                            Storage::disk('public')->move($old_path, $new_path);
                            
                            // ✅ Update the image filename in the database
                            $product_category->image = $new_filename;
                        }
                    }
                }
            }
        });

        static::deleting(function ($product_category) {
            if ($product_category->image) {
                $path = "product-categories/{$product_category->image}";
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->image) {
            return asset('/assets/images/default-image.png');
        }

        return asset("storage/product-categories/{$this->image}");
    }
}