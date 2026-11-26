<?php

namespace App\Services\Payroll;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private PayrollCalculator $calculator) {}

    /**
     * Genera (o regenera) las nóminas de un periodo en borrador.
     *
     * @return int número de nóminas generadas
     */
    public function generate(PayrollPeriod $period): int
    {
        return DB::transaction(function () use ($period) {
            $period = $this->lockDraft($period);

            $start = CarbonImmutable::create($period->year, $period->month, 1);
            $end = $start->endOfMonth();

            // Regenerar = borrar y volver a calcular; las líneas caen por ON DELETE CASCADE
            $period->payslips()->delete();

            $contractInPeriod = fn ($query) => $query
                ->where('start_date', '<=', $end)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start));

            // shortcut: sin prorrateo; quien está de baja lógica o entra a mitad de mes cobra nada o el mes entero
            $employees = Employee::query()
                ->where('status', 'active')
                ->where('hire_date', '<=', $end)
                ->whereHas('contracts', $contractInPeriod)
                ->with([
                    'user', 'department', 'position',
                    'contracts' => fn ($q) => $contractInPeriod($q)->latest('start_date'),
                ])
                ->get();

            foreach ($employees as $employee) {
                // Si hubo cambio de contrato en el mes, manda el más reciente
                $this->createPayslip($period, $employee, $employee->contracts->first(), $end);
            }

            return $employees->count();
        });
    }

    public function close(PayrollPeriod $period): PayrollPeriod
    {
        return DB::transaction(function () use ($period) {
            $period = $this->lockDraft($period);

            if (! $period->payslips()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'No se puede cerrar un periodo sin nóminas generadas.',
                ]);
            }

            $period->update(['status' => 'closed']);

            return $period;
        });
    }

    // El bloqueo de la fila del periodo evita que se genere y se cierre a la vez
    private function lockDraft(PayrollPeriod $period): PayrollPeriod
    {
        $locked = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'El periodo está cerrado.']);
        }

        return $locked;
    }

    private function createPayslip(PayrollPeriod $period, Employee $employee, Contract $contract, CarbonImmutable $end): void
    {
        // Carbon 3 devuelve decimales: solo cuentan los años completos
        $seniorityYears = (int) $employee->hire_date->diffInYears($end);
        $result = $this->calculator->calculate($contract->base_salary, $seniorityYears);

        $payslip = $period->payslips()->create([
            'employee_id' => $employee->id,
            'gross' => $result['gross'],
            'deductions' => $result['deductions'],
            'net' => $result['net'],
            // Copia de los datos usados: la nómina no depende de cambios posteriores
            'snapshot' => [
                'employee_name' => $employee->user->name,
                'department' => $employee->department->name,
                'position' => $employee->position->name,
                'hire_date' => $employee->hire_date->toDateString(),
                'seniority_years' => $seniorityYears,
                'contract_type' => $contract->type,
                'base_salary' => $contract->base_salary,
                'year' => $period->year,
                'month' => $period->month,
            ],
        ]);

        $payslip->lines()->createMany($result['lines']);
    }
}
