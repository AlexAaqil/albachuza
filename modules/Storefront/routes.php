<?php

use Illuminate\Support\Facades\Route;
use Modules\Storefront\Http\Controllers\HomePageController;
use Modules\Storefront\Http\Controllers\GuestProductController;
use Modules\ContactMessage\Http\Controllers\ContactMessageController;

Route::get('/', [HomePageController::class, 'index'])->name('home');
Route::inertia('/about', 'storefront/about/Index')->name('about-page');
Route::get('/contact', [ContactMessageController::class, 'create'])->name('contact-messages.create');
Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact-messages.store');
Route::get('/shop', [GuestProductController::class, 'shop'])->name('shop-page.index');
Route::get('/product-details/{product:slug}', [GuestProductController::class, 'productDetails'])->name('product-details.index');