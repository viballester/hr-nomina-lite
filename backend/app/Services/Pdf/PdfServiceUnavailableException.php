<?php

namespace App\Services\Pdf;

use Illuminate\Http\JsonResponse;
use RuntimeException;

// Excepción de dominio: el controlador no necesita saber nada de HTTP ni de Guzzle
class PdfServiceUnavailableException extends RuntimeException
{
    // Laravel llama a render() automáticamente al convertir la excepción en respuesta
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'El servicio de PDF no está disponible. Inténtalo más tarde.'], 503);
    }
}
