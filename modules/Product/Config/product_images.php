<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    | Product photos are stored on this filesystem disk, under this directory.
    | New photos are saved in "<directory>/<product id>/<uuid>.webp".
    */
    'disk' => env('PRODUCT_IMAGES_DISK', 'public'),
    'directory' => 'products',

    /*
    |--------------------------------------------------------------------------
    | Shape
    |--------------------------------------------------------------------------
    | Every photo is placed on a square canvas with this background colour so
    | that nothing is ever cropped (important for shoes: toes and soles).
    | Small photos are never enlarged, the canvas is only as big as the photo.
    */
    'background' => 'ffffff',

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    | The browser shrinks photos before uploading, so these limits are only a
    | safety net for uploads that skip that step (e.g. bulk import).
    */
    'max_per_product' => 5,
    'max_upload_kb' => 10240,   // 10 MB
    'max_dimension' => 6000,    // longest side in pixels (24 megapixel photo)

    // Raised temporarily while an image is processed (GD needs ~4 bytes/pixel).
    'memory_limit' => '512M',

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    | size    : side length of the square canvas, in pixels
    | quality : WebP quality, 1-100
    */
    'variants' => [
        'full' => ['size' => 1200, 'quality' => 82], // product page
        'card' => ['size' => 600,  'quality' => 80], // storefront grid / carousel
        'thumb' => ['size' => 300, 'quality' => 75], // admin tables, POS, thumbnails
    ],

];