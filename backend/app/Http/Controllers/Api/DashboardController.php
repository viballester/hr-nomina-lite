<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Services\LeaveRequestService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Un único endpoint con los KPIs de la pantalla de inicio, filtrados por rol con las mismas reglas que los listados
    public function __invoke(Request $request, LeaveRequestService $leaveService)
    {
        $user = $request->user();
        $employee = $user->employee;
        abort_if($employee === null && $user->role !== 'admin', 403, 'Tu usuario no tiene ficha de empleado.');

        $today = now()->toDateString();

        return ['data' => [
            // Plantilla: solo para quien gestiona personas (el manager, la de su departamento)
            'active_employees' => match ($user->role) {
                'admin' => Employee::where('status', 'active')->count(),
                'manager' => Employee::where('status', 'active')->where('department_id', $employee->department_id)->count(),
                default => null,
            },
            'pending_requests' => LeaveRequest::visibleTo($user)->where('status', 'pending')->count(),
            'absent_today' => LeaveRequest::visibleTo($user)->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
            'leave_days_available' => $employee ? $leaveService->balanceSummary($employee, now()->year)['days_available'] ?? null : null,
            'last_payroll' => $user->role === 'admin' ? $this->lastPeriod() : $this->lastOwnPayslip($employee),
        ]];
    }

    private function lastPeriod(): ?array
    {
        $period = PayrollPeriod::withCount('payslips')->withSum('payslips', 'net')
            ->orderByDesc('year')->orderByDesc('month')->first();

        return $period ? [
            'year' => $period->year,
            'month' => $period->month,
            'status' => $period->status,
            'payslips_count' => $period->payslips_count,
            'net' => number_format((float) $period->payslips_sum_net, 2, '.', ''),
        ] : null;
    }

    // Como en el listado: el empleado solo ve nóminas de periodos cerrados
    private function lastOwnPayslip(Employee $employee): ?array
    {
        $payslip = $employee->payslips()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payslips.payroll_period_id')
            ->where('payroll_periods.status', 'closed')
            ->orderByDesc('payroll_periods.year')->orderByDesc('payroll_periods.month')
            ->first(['payslips.id', 'payslips.net', 'payroll_periods.year', 'payroll_periods.month']);

        return $payslip ? [
            'id' => $payslip->id,
            'year' => $payslip->year,
            'month' => $payslip->month,
            'status' => 'closed',
            'net' => $payslip->net,
        ] : null;
    }
}
