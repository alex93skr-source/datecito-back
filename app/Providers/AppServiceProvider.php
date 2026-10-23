<?php

namespace App\Providers;

use App\Contracts\PurchaseVerifier;
use App\Models\User;
use App\Services\Billing\FakePurchaseVerifier;
use App\Services\Billing\StorePurchaseVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Las compras simuladas sólo existen en local/pruebas, aunque alguien active la variable en producción.
        $this->app->bind(PurchaseVerifier::class, fn () => config('datecito.fake_purchases') && $this->app->environment('local', 'testing')
            ? new FakePurchaseVerifier
            : new StorePurchaseVerifier);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        Gate::define('moderate', fn (User $user) => $user->canModerate());
        // Sólo admins: roles del equipo, membresías, saldo, eliminar cuentas.
        Gate::define('administer', fn (User $user) => $user->isAdmin());

        $this->configureRateLimiting();
    }

    /**
     * Límites por persona (o por correo + IP cuando aún no hay sesión).
     */
    private function configureRateLimiting(): void
    {
        $byUser = fn (Request $request) => $request->user()?->id ?? $request->ip();

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('codes', fn (Request $request) => Limit::perMinute(5)->by($byUser($request)));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($byUser($request)));
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(30)->by($byUser($request)));
        RateLimiter::for('swipes', fn (Request $request) => Limit::perMinute(90)->by($byUser($request)));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(30)->by($byUser($request)));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(10)->by($byUser($request)));
        RateLimiter::for('purchases', fn (Request $request) => Limit::perMinute(10)->by($byUser($request)));
        RateLimiter::for('events', fn (Request $request) => Limit::perMinute(20)->by($byUser($request)));
    }
}
