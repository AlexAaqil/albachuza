<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Support\Concerns\HasUuid;
use Modules\Support\Concerns\HasSlug;

class ProductCategory extends Model
{
    use HasUuid, HasSlug;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}