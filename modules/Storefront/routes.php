<?php

use Illuminate\Support\Facades\Route;
use Modules\Storefront\Http\Controllers\HomePageController;
use Modules\Storefront\Http\Controllers\GuestProductController;

Route::get('/', [HomePageController::class, 'index'])->name('home');
Route::get('/shop', [GuestProductController::class, 'shop'])->name('shop-page.index');
Route::get('/product-details/{product:slug}', [GuestProductController::class, 'productDetails'])->name('product-details.index');