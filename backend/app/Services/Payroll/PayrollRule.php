<?php

namespace App\Services\Payroll;

/** Un concepto de la nómina. Para añadir uno nuevo se crea otra clase; el cálculo no cambia. */
interface PayrollRule
{
    public function concept(): string;

    /** 'earning' (devengo) o 'deduction' (deducción) */
    public function type(): string;

    /** Importe en céntimos. $grossCents vale 0 mientras se calculan los devengos. */
    public function amount(int $baseCents, int $seniorityYears, int $grossCents): int;
}
