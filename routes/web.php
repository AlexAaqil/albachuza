<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::inertia('/about', 'storefront/about/Index')->name('about-page');
Route::inertia('/contact', 'storefront/contact/Index')->name('contact-page');

Route::inertia('/welcome', 'Welcome')->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
