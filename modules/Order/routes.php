<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;
use Modules\Order\Http\Controllers\EcommerceOrderController;

Route::middleware('role:admin,super_admin')->group(function ()
{
    Route::prefix('orders')
        ->name('orders.')
        ->controller(OrderController::class)
        ->group(function ()
    {
        Route::delete('/{order:uuid}', 'destroy')->name('destroy');
    });
});

Route::middleware('role:admin,super_admin,cashier')->group(function ()
{
    Route::prefix('orders')
        ->name('orders.')
        ->controller(OrderController::class)
        ->group(function ()
    {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{order:uuid}/edit', 'edit')->name('edit');
        Route::put('/{order:uuid}', 'update')->name('update');
    });
});

Route::post('/shop/orders', [EcommerceOrderController::class, 'store'])
    ->name('shop.orders.store');

Route::get('/shop/orders/{order}/thank-you', function (\Modules\Order\Models\Order $order) {
    return inertia('app/shop/orders/ThankYou', [
        'order' => [
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'total_selling_price' => $order->total_selling_price,
            'customer_name' => $order->customer_name,
        ],
    ]);
})->name('shop.orders.thank-you');

