<?php

namespace Modules\Product\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Product\Console\RegenerateProductImages;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge a custom config file
        $this->mergeConfigFrom(__DIR__ . '/../Config/product_images.php', 'product_images');

        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([RegenerateProductImages::class]);
        }
    }
}