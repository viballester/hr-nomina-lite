<?php

namespace App\Services;

use Carbon\CarbonInterface;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function countWorkingDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $days = 0;
        $current = $start->copy();

        while($current->lte($end)) {
            if(!$current->isWeekend()) {
                $days++;
            }

            $current->addDay();
        }
        return $days;
    }

    public function create(Employee $employee, LeaveType $type, CarbonInterface $start, CarbonInterface $end): LeaveRequest
    {
        // shortcut: no admite solicitudes que crucen de año; partirlas en dos si hiciera falta
        if ($start->year !== $end->year) {
            throw ValidationException::withMessages(['end_date' => 'La ausencia no puede cruzar de año.']);
        }

        // 1. Calcular días laborables
        $days = $this->countWorkingDays($start, $end);

        if ($days === 0) {
            throw ValidationException::withMessages([
                'start_date' => 'El periodo no incluye días laborables.',
            ]);
        }

        // 2. Comprobar solapamientos
        $hasOverlap = $employee->leaveRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya tienes una ausencia en esas fechas.',
            ]);
        }

        // 3. Comprobar saldo disponible
        if ($type->deducts_balance) {
            $balance = $employee->leaveBalances()
                ->where('year', $start->year)
                ->first();

            $pending = $employee->leaveRequests()
                ->where('status', 'pending')
                ->whereYear('start_date', $start->year)
                ->sum('days');

            if (
                !$balance ||
                ($balance->days_total - $balance->days_used - $pending) < $days
            ) {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'Saldo insuficiente.',
                ]);
            }
        }

        // 4. Crear solicitud
        $leaveRequest = $employee->leaveRequests()->make([
            'leave_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
        ]);

        // status no está en fillable: se asigna aparte
        $leaveRequest->status = 'pending';
        $leaveRequest->save();

        return $leaveRequest;
    }
}