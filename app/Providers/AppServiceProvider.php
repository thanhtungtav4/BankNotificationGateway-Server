<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind gateway services here as implementation grows.
    }

    public function boot(): void
    {
        // Add model observers, macros, and production policies here.
    }
}
