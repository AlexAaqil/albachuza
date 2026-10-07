<?php

namespace Modules\Product\Exceptions;

use RuntimeException;

class StockTrackingDisabledException extends RuntimeException
{
    public static function forProduct(\Modules\Product\Models\Product $product): self
    {
        return new self(
            "Stock tracking is disabled for '{$product->name}'. Enable it before adjusting stock."
        );
    }
}