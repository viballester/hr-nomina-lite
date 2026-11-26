<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PercentageDeductionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayrollCalculatorTest extends TestCase
{
    public function test_calculates_lines_and_totals(): void
    {
        $result = (new PayrollCalculator)->calculate('2000.00', 7);

        $this->assertSame('2060.00', $result['gross']);
        $this->assertSame('439.81', $result['deductions']);
        $this->assertSame('1620.19', $result['net']);
        $this->assertSame(['2000.00', '60.00', '309.00', '130.81'], array_column($result['lines'], 'amount'));
    }

    public static function seniority(): array
    {
        return [
            'sin trienio' => [2, '1000.00'],
            'primer trienio' => [3, '1030.00'],
            'casi segundo' => [5, '1030.00'],
            'segundo trienio' => [6, '1060.00'],
        ];
    }

    #[DataProvider('seniority')]
    public function test_seniority_counts_complete_trienniums(int $years, string $gross): void
    {
        $this->assertSame($gross, (new PayrollCalculator)->calculate('1000.00', $years)['gross']);
    }

    public function test_zero_amount_rules_add_no_line(): void
    {
        $result = (new PayrollCalculator)->calculate('1000.00', 0);

        $this->assertNotContains('Antigüedad', array_column($result['lines'], 'concept'));
    }

    public function test_half_cent_rounds_up(): void
    {
        $rule = new PercentageDeductionRule('x', 1500);

        $this->assertSame(2, $rule->amount(0, 0, 10)); // 1,5 céntimos
        $this->assertSame(1, $rule->amount(0, 0, 9));  // 1,35 céntimos
    }
}
