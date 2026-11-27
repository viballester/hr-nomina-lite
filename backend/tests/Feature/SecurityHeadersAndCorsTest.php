<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersAndCorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_respuestas_de_la_api_llevan_cabeceras_de_seguridad(): void
    {
        // Incluso un 401 debe llevarlas.
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_cors_permite_el_origen_del_frontend(): void
    {
        $this->call('OPTIONS', '/api/login', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_cors_no_autoriza_otros_origenes(): void
    {
        $response = $this->call('OPTIONS', '/api/login', server: [
            'HTTP_ORIGIN' => 'https://atacante.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        // El navegador solo permite la respuesta si este valor coincide con su origen.
        $this->assertNotSame('https://atacante.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
