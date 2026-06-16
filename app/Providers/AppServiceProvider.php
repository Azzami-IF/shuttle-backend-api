<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        if (request()->has('lang')) {
            session(['locale' => request('lang')]);
        }
        
        if (session()->has('locale')) {
            app()->setLocale(session('locale'));
        }
    }
}
