<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

class PayslipPolicy
{
    // El salario es sensible: el manager tampoco ve las nóminas de su equipo
    public function view(User $user, Payslip $payslip): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $payslip->employee_id === $user->employee?->id
            && $payslip->payrollPeriod->status === 'closed';
    }
}
