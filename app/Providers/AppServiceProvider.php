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
        $this->ensureWindowsOpenSsl();

        \Illuminate\Support\Facades\View::composer('partials.topbar', function ($view) {
            if (auth()->check() && ! auth()->user()->relationLoaded('role')) {
                auth()->user()->load('role');
            }
        });
    }

    /**
     * XAMPP on Windows often cannot mint EC keys unless OPENSSL_CONF is set.
     */
    private function ensureWindowsOpenSsl(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || getenv('OPENSSL_CONF')) {
            return;
        }

        foreach ([
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
        ] as $cnf) {
            if (is_file($cnf)) {
                putenv('OPENSSL_CONF=' . $cnf);
                return;
            }
        }
    }
}
