<?php

namespace App\Providers;

use App\Services\Mail\ProductionMailTransportGuard;
use Illuminate\Support\ServiceProvider;

final class ProductionMailServiceProvider extends ServiceProvider
{
    public function boot(ProductionMailTransportGuard $guard): void
    {
        $guard->assertSafeForProduction();
    }
}
