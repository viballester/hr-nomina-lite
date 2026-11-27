<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Red de seguridad: recorre TODAS las rutas de la API y exige 401 sin token.
 * Si mañana alguien añade una ruta fuera del grupo auth:sanctum, este test falla.
 */
class ProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_ROUTES = ['api/login'];

    public function test_todas_las_rutas_de_la_api_exigen_autenticacion(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), self::PUBLIC_ROUTES)) {
                continue;
            }

            // Los parámetros ({employee}, {payslip}...) se rellenan con 1: el 401 debe llegar antes de buscar el modelo.
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');

            $this->json($method, '/'.$uri)->assertStatus(401, "$method /$uri no exige token");
            $checked++;
        }

        $this->assertGreaterThan(20, $checked); // evita que el test pase en vacío
    }
}
