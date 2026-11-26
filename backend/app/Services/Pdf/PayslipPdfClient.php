<?php

namespace App\Services\Pdf;

use App\Models\Payslip;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP del pdf-service (Node). Traduce una nómina al contrato JSON
 * del servicio y devuelve el PDF en binario. No decide permisos: eso ya lo
 * ha hecho la Policy antes de llamar aquí.
 */
class PayslipPdfClient
{
    public function render(Payslip $payslip): string
    {
        $payslip->loadMissing('lines');
        $snapshot = $payslip->snapshot;

        // Datos del snapshot, no del empleado actual: el recibo refleja el momento del cálculo
        $payload = [
            'employee' => $snapshot['employee_name'],
            'department' => $snapshot['department'],
            'position' => $snapshot['position'],
            'year' => $snapshot['year'],
            'month' => $snapshot['month'],
            // Los casts decimal:2 ya devuelven strings "1234.56": nunca float (D-005)
            'gross' => $payslip->gross,
            'deductions' => $payslip->deductions,
            'net' => $payslip->net,
            'lines' => $payslip->lines->map->only(['concept', 'type', 'amount'])->values()->all(),
        ];

        try {
            return Http::withToken((string) config('services.pdf.token'))
                ->timeout(config('services.pdf.timeout'))
                ->accept('application/pdf')
                ->post(rtrim(config('services.pdf.url'), '/').'/payslips/pdf', $payload)
                ->throw()
                ->body();
        } catch (ConnectionException|RequestException $e) {
            // Se registra el detalle técnico; al usuario solo le llega un 503 genérico
            Log::error('pdf-service no disponible', ['payslip_id' => $payslip->id, 'error' => $e->getMessage()]);
            throw new PdfServiceUnavailableException(previous: $e);
        }
    }
}
