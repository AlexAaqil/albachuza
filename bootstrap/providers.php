<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Modules\Support\Providers\SupportServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,

    SupportServiceProvider::class,
];
