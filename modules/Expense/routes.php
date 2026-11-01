<?php

use Illuminate\Support\Facades\Route;
use Modules\Expense\Http\Controllers\ExpenseCategoryController;
use Modules\Expense\Http\Controllers\ExpenseController;

Route::middleware('role:admin,super_admin')->group(function () {
    Route::prefix('expense-categories')
        ->name('expense-categories.')
        ->controller(ExpenseCategoryController::class)
        ->group(function ()
    {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{expense_category:uuid}/edit', 'edit')->name('edit');
        Route::put('/{expense_category:uuid}', 'update')->name('update');
        Route::delete('/{expense_category:uuid}', 'destroy')->name('destroy');
    });

    Route::prefix('expenses')
        ->name('expenses.')
        ->controller(ExpenseController::class)
        ->group(function () 
    {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{expense:uuid}/edit', 'edit')->name('edit');
        Route::put('/{expense:uuid}', 'update')->name('update');
        Route::delete('/{expense:uuid}', 'destroy')->name('destroy');
        Route::delete('/bulk', 'bulkDelete')->name('bulk-delete');
    });
});
