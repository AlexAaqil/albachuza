<?php

namespace Modules\Product\Exceptions;

use Modules\Product\Models\Product;
use RuntimeException;

class StockTrackingDisabledException extends RuntimeException
{
    public static function forProduct(Product $product): self
    {
        return new self(
            "Stock tracking is disabled for '{$product->name}'. Enable it before adjusting stock."
        );
    }
}
