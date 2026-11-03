<?php declare(strict_types=1);
namespace App\Providers;

use App\Services\{AuthService, JwtService};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
        $this->app->singleton(AuthService::class, fn ($app) => new AuthService($app->make(JwtService::class)));
    }

    public function boot(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
