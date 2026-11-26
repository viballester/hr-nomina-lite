<?php

namespace App\Services\Payroll;

class BaseSalaryRule implements PayrollRule
{
    public function concept(): string
    {
        return 'Salario base';
    }

    public function type(): string
    {
        return 'earning';
    }

    public function amount(int $baseCents, int $seniorityYears, int $grossCents): int
    {
        return $baseCents;
    }
}
