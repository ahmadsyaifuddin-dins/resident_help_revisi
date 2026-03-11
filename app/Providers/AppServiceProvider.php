<?php

namespace App\Providers;

use App\Traits\SystemIntegrityTrait;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    use SystemIntegrityTrait;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->runningInConsole()) {
            return;
        }
        $this->_verifySystemIntegrity();
    }
}
