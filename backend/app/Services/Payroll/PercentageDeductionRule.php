<?php

namespace App\Services\Payroll;

/** Deducción porcentual sobre el bruto. El tipo va en puntos básicos (635 = 6,35 %) para no usar float. */
class PercentageDeductionRule implements PayrollRule
{
    public function __construct(
        private string $concept,
        private int $basisPoints,
    ) {}

    public function concept(): string
    {
        return $this->concept;
    }

    public function type(): string
    {
        return 'deduction';
    }

    public function amount(int $baseCents, int $seniorityYears, int $grossCents): int
    {
        // Redondeo al céntimo (mitades hacia arriba): sumar medio divisor antes de la división entera
        return intdiv($grossCents * $this->basisPoints + 5000, 10000);
    }
}
