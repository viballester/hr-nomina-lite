<?php

namespace App\Services\Payroll;

/**
 * Simulación didáctica SIN validez legal: no implementa IRPF ni Seguridad Social reales.
 * Opera en céntimos enteros y devuelve los importes como texto "1234.56" (columnas DECIMAL).
 */
class PayrollCalculator
{
    /** @param PayrollRule[] $rules */
    public function __construct(
        private array $rules = [
            new BaseSalaryRule,
            new SeniorityRule,
            new PercentageDeductionRule('Retención IRPF (simulada)', 1500),
            new PercentageDeductionRule('Cotización Seguridad Social (simulada)', 635),
        ],
    ) {}

    /**
     * @param  string  $baseSalary  con dos decimales, tal como lo entrega el cast decimal:2
     * @return array{gross: string, deductions: string, net: string, lines: list<array{concept: string, type: string, amount: string}>}
     */
    public function calculate(string $baseSalary, int $seniorityYears): array
    {
        $baseCents = (int) str_replace('.', '', $baseSalary);
        $totals = ['earning' => 0, 'deduction' => 0];
        $lines = [];

        // Primero todos los devengos: las deducciones necesitan el bruto completo
        foreach (['earning', 'deduction'] as $type) {
            $grossCents = $type === 'deduction' ? $totals['earning'] : 0;

            foreach ($this->rules as $rule) {
                if ($rule->type() !== $type) {
                    continue;
                }

                $cents = $rule->amount($baseCents, $seniorityYears, $grossCents);

                if ($cents === 0) {
                    continue;
                }

                $totals[$type] += $cents;
                $lines[] = ['concept' => $rule->concept(), 'type' => $type, 'amount' => $this->format($cents)];
            }
        }

        return [
            'gross' => $this->format($totals['earning']),
            'deductions' => $this->format($totals['deduction']),
            'net' => $this->format($totals['earning'] - $totals['deduction']),
            'lines' => $lines,
        ];
    }

    // shortcut: solo importes no negativos; revisar si se añaden regularizaciones negativas
    private function format(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
