<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras defensivas para las respuestas de la API (JSON y PDF).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');   // el navegador no "adivina" el tipo
        $response->headers->set('X-Frame-Options', 'DENY');             // nadie puede meternos en un iframe (clickjacking)
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'"); // la API no sirve HTML ejecutable
        $response->headers->set('Cache-Control', 'no-store, private');  // datos personales y salarios: no cachear

        return $response;
    }
}
