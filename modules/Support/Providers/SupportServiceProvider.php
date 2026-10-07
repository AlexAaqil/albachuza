<?php

namespace Modules\Support\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Support\Http\Middleware\RoleMiddleware;
use Modules\User\Providers\UserServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(UserServiceProvider::class);
        $this->app->register(\Modules\Product\Providers\ProductServiceProvider::class);
        // $this->app->register(\Modules\Order\Providers\OrderServiceProvider::class);
        // $this->app->register(\Modules\Payment\Providers\PaymentServiceProvider::class);
        // $this->app->register(\Modules\StoreBranch\Providers\BranchServiceProvider::class);
    }

    public function boot(Router $router): void
    {
        // Register 'role' middleware alias globally
        $router->aliasMiddleware('role', RoleMiddleware::class);
    }
}
