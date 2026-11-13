<?php

namespace App\Services;

use Carbon\CarbonInterface;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

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
            $summary = $this->balanceSummary($employee, $start->year);

            if (!$summary || $summary['days_available'] < $days) {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'Saldo insuficiente.',
                ]);
            }
        }


        // 4. Crear solicitud (y descontar saldo si se autoaprueba), todo o nada
        return DB::transaction(function () use ($employee, $type, $start, $end, $days) {
            $leaveRequest = $employee->leaveRequests()->make([
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
            ]);

            // status no está en fillable: se asigna aparte
            $leaveRequest->status = $type->requires_approval ? 'pending' : 'approved';
            $leaveRequest->save();

            if (!$type->requires_approval && $type->deducts_balance) {
                $employee->leaveBalances()
                    ->where('year', $start->year)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->increment('days_used', $days);
            }

            return $leaveRequest;
        });
    }

    private function lockPending(LeaveRequest $request): LeaveRequest
    {
        $locked = LeaveRequest::query()
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden revisar solicitudes pendientes.',
            ]);
        }

        return $locked;
    }

    public function approve(LeaveRequest $leaveRequest, Employee $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $reviewer) {

            $leaveRequest = $this->lockPending($leaveRequest);

            if ($leaveRequest->leaveType->deducts_balance) {
                $leaveRequest->employee
                    ->leaveBalances()
                    ->where('year', $leaveRequest->start_date->year)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->increment('days_used', $leaveRequest->days);
            }

            $leaveRequest->status = 'approved';
            $leaveRequest->reviewed_by = $reviewer->id;
            $leaveRequest->reviewed_at = now();

            $leaveRequest->save();

            return $leaveRequest;
        });
    }

    public function reject(LeaveRequest $leaveRequest, Employee $reviewer, string $comment): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $reviewer, $comment) {

            $leaveRequest = $this->lockPending($leaveRequest);

            $leaveRequest->status = 'rejected';
            $leaveRequest->review_comment = $comment;
            $leaveRequest->reviewed_by = $reviewer->id;
            $leaveRequest->reviewed_at = now();

            $leaveRequest->save();

            return $leaveRequest;
        });
    }


    public function cancel(LeaveRequest $leaveRequest): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest) {

            $locked = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !in_array($locked->status, ['pending', 'approved']) ||
                $locked->start_date->lte(today())
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden cancelar solicitudes pendientes o aprobadas que no hayan empezado.',
                ]);
            }

            if (
                $locked->status === 'approved' &&
                $locked->leaveType->deducts_balance
            ) {
                $locked->employee
                    ->leaveBalances()
                    ->where('year', $locked->start_date->year)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->decrement('days_used', $locked->days);
            }

            $locked->status = 'cancelled';
            $locked->save();

            return $locked;
        });
    }


    public function balanceSummary(Employee $employee, int $year): ?array
    {
        $balance = $employee->leaveBalances()
            ->where('year', $year)
            ->first();

        if (!$balance) {
            return null;
        }

        $pending = $employee->leaveRequests()
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->sum('days');

        return [
            'days_total' => (int) $balance->days_total,
            'days_used' => (int) $balance->days_used,
            'days_pending' => (int) $pending,
            'days_available' => (int) (
                $balance->days_total - $balance->days_used - $pending
            ),
        ];
    }



}