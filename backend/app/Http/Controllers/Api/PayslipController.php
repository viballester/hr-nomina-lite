<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Pdf\PayslipPdfClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->employee === null && $user->role !== 'admin', 403, 'Tu usuario no tiene ficha de empleado.');

        // Cerrado por defecto: solo el admin ve las ajenas y los borradores
        $query = match ($user->role) {
            'admin' => Payslip::query(),
            'manager', 'employee' => Payslip::where('employee_id', $user->employee->id)
                ->whereRelation('payrollPeriod', 'status', 'closed'),
            default => abort(403),
        };

        $payslips = $query->with('payrollPeriod')
            ->when($request->query('period_id'), fn ($q, $id) => $q->where('payroll_period_id', $id))
            // Lo más reciente primero: el id del periodo no garantiza el orden cronológico
            ->orderByDesc(PayrollPeriod::selectRaw('year * 100 + month')->whereColumn('payroll_periods.id', 'payslips.payroll_period_id'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return PayslipResource::collection($payslips);
    }

    public function show(Payslip $payslip)
    {
        Gate::authorize('view', $payslip);

        return new PayslipResource($payslip->load('lines', 'payrollPeriod'));
    }

    // Mismo permiso que ver la nómina: quien no puede verla tampoco descarga el PDF
    public function pdf(Payslip $payslip, PayslipPdfClient $client)
    {
        Gate::authorize('view', $payslip);

        $filename = sprintf('nomina-%d-%02d-%d.pdf', $payslip->snapshot['year'], $payslip->snapshot['month'], $payslip->id);

        return response($client->render($payslip), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
