<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductCategoryController;
use Modules\Product\Http\Controllers\ProductBrandController;
use Modules\Product\Http\Controllers\ProductInventoryController;

Route::middleware('role:admin,super_admin,cashier')->group(function ()
{
    Route::prefix('products')
        ->name('products.')
        ->controller(ProductController::class)
        ->group(function ()
    {
        Route::get('/', 'index')->name('index');
    });
    
    Route::prefix('product-categories')
        ->name('product-categories.')
        ->controller(ProductCategoryController::class)
        ->group(function ()
    {
        Route::get('/', 'index')->name('index');
    });

    Route::prefix('product-brands')
        ->name('product-brands.')
        ->controller(ProductBrandController::class)
        ->group(function ()
    {
        Route::get('/', 'index')->name('index');
    });
});

Route::middleware('role:admin,super_admin')->group(function ()
{
    Route::prefix('products')
        ->name('products.')
        ->controller(ProductController::class)
        ->group(function ()
    {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{product:uuid}/edit', 'edit')->name('edit');
        Route::put('/{product:uuid}', 'update')->name('update');
        Route::post('/products/{product:uuid}/toggle-attribute', 'toggleAttribute')->name('toggle-attribute');
        Route::delete('/{product:uuid}', 'destroy')->name('destroy');
    });
    
    Route::prefix('product-categories')
        ->name('product-categories.')
        ->controller(ProductCategoryController::class)
        ->group(function ()
    {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{product_category:uuid}/edit', 'edit')->name('edit');
        Route::put('/{product_category:uuid}', 'update')->name('update');
        Route::delete('/{product_category:uuid}', 'destroy')->name('destroy');
    });

    Route::prefix('product-brands')
        ->name('product-brands.')
        ->controller(ProductBrandController::class)
        ->group(function ()
    {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{product_brand:uuid}/edit', 'edit')->name('edit');
        Route::put('/{product_brand:uuid}', 'update')->name('update');
        Route::delete('/{product_brand:uuid}', 'destroy')->name('destroy');
    });

    Route::prefix('products-inventory')
        ->name('products-inventory.')
        ->controller(ProductInventoryController::class)
        ->group( function()
    {
        Route::get('/', 'index')->name('index');
        Route::get('{product:uuid}/create', 'create')->name('create');
        Route::post('{product:uuid}/', 'store')->name('store');
        Route::get('/{product:uuid}/edit', 'edit')->name('edit');
        Route::put('/{product:uuid}', 'update')->name('update');
        Route::get('/{product:uuid}/history', 'history')->name('history');
    });
});

