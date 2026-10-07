<?php

use Illuminate\Support\Facades\Route;
use Modules\Storefront\Http\Controllers\HomePageController;

Route::get('/', [HomePageController::class, 'index'])->name('home');