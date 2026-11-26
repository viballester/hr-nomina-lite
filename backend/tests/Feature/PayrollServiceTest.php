<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Position;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = PayrollPeriod::create(['year' => 2026, 'month' => 11])->refresh();
    }

    private function employee(array $contract = [], array $attributes = []): Employee
    {
        $employee = Employee::factory()->create($attributes + [
            'department_id' => Department::firstOrCreate(['name' => 'Nóminas'])->id,
            'position_id' => Position::firstOrCreate(['name' => 'Técnico'])->id,
            'hire_date' => '2019-11-30',
        ]);
        $employee->contracts()->create($contract + [
            'type' => 'indefinido', 'base_salary' => '2000.00', 'start_date' => '2019-11-30',
        ]);

        return $employee;
    }

    private function service(): PayrollService
    {
        return app(PayrollService::class);
    }

    public function test_genera_nomina_con_lineas_y_snapshot(): void
    {
        $this->employee();

        $this->assertSame(1, $this->service()->generate($this->period));

        $payslip = Payslip::sole();
        $this->assertSame('1620.19', $payslip->net);
        $this->assertSame(4, $payslip->lines()->count());
        $this->assertSame(7, $payslip->snapshot['seniority_years']); // cumple 7 el último día del mes
        $this->assertSame('2000.00', $payslip->snapshot['base_salary']);
    }

    public function test_solo_incluye_activos_con_contrato_en_el_periodo(): void
    {
        $included = $this->employee(['end_date' => '2026-11-01']); // termina el primer día
        $this->employee(['end_date' => '2026-10-31']);
        $this->employee(['start_date' => '2026-12-01']);
        $this->employee(attributes: ['status' => 'inactive']);

        $this->assertSame(1, $this->service()->generate($this->period));
        $this->assertSame($included->id, Payslip::sole()->employee_id);
    }

    public function test_usa_el_contrato_mas_reciente(): void
    {
        $employee = $this->employee(['end_date' => '2026-11-14']);
        $employee->contracts()->create(['type' => 'indefinido', 'base_salary' => '3000.00', 'start_date' => '2026-11-15']);

        $this->service()->generate($this->period);

        $this->assertSame('3000.00', Payslip::sole()->snapshot['base_salary']);
    }

    public function test_regenerar_no_duplica_y_refleja_cambios(): void
    {
        $employee = $this->employee();
        $this->service()->generate($this->period);
        $employee->contracts()->first()->update(['base_salary' => '2500.00']);

        $this->service()->generate($this->period);

        $this->assertSame('2500.00', Payslip::sole()->snapshot['base_salary']);
        $this->assertSame(4, PayslipLine::count()); // las antiguas se borran en cascada
    }

    public function test_periodo_cerrado_no_se_regenera_ni_cambia(): void
    {
        $employee = $this->employee();
        $this->service()->generate($this->period);
        $this->assertSame('closed', $this->service()->close($this->period)->status);
        $employee->contracts()->first()->update(['base_salary' => '2500.00']);

        try {
            $this->service()->generate($this->period);
            $this->fail('Debía rechazar un periodo cerrado');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('1620.19', Payslip::sole()->net);
    }

    public function test_no_cierra_un_periodo_vacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->close($this->period);
    }
}
