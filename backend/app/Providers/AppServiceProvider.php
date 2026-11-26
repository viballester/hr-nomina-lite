<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Login: frena la fuerza bruta. Clave email+IP para no bloquear a toda una oficina tras un mismo NAT.
        // El límite por IP frena además el "password spraying" (una IP probando muchos emails).
        RateLimiter::for('login', function (Request $request) {
            $tooMany = fn (Request $request, array $headers) => response()->json(
                ['message' => 'Demasiados intentos. Vuelve a intentarlo en un minuto.'],
                429,
                $headers,
            );

            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip())->response($tooMany),
                Limit::perMinute(20)->by($request->ip())->response($tooMany),
            ];
        });

        // API autenticada: límite por usuario (o IP si no hay sesión).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
