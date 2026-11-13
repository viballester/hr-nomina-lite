<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeaveRequestPolicy
{
    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->employee_id === $user->employee?->id) {
            return false; // nadie se aprueba a sí mismo, ni el admin
        }
    
        if ($user->role === 'admin') {
            return true;
        }
    
        return $user->role === 'manager'
            && $leaveRequest->employee->department_id === $user->employee?->department_id;
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->employee_id === $user->employee?->id;
    }
}
