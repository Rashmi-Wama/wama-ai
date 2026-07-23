<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // XAMPP/Windows PHP often has empty curl.cainfo; use a bundled CA file for HTTPS APIs (Groq).
        $caBundle = storage_path('certs/cacert.pem');
        if (is_file($caBundle)) {
            Http::globalOptions([
                'verify' => $caBundle,
            ]);
        }
    }
}
