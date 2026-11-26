<?php

namespace App\Services\Payroll;

/** Complemento ficticio: 30,00 € por cada trienio completo. */
class SeniorityRule implements PayrollRule
{
    private const CENTS_PER_TRIENNIUM = 3000;

    public function concept(): string
    {
        return 'Antigüedad';
    }

    public function type(): string
    {
        return 'earning';
    }

    public function amount(int $baseCents, int $seniorityYears, int $grossCents): int
    {
        return intdiv($seniorityYears, 3) * self::CENTS_PER_TRIENNIUM;
    }
}
