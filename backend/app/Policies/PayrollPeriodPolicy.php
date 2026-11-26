<?php

namespace App\Policies;

use App\Models\User;

class PayrollPeriodPolicy
{
    // Los periodos (ver, crear, generar y cerrar) son solo de administración
    public function manage(User $user): bool
    {
        return $user->role === 'admin';
    }
}
