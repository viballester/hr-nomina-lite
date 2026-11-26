<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private Employee $manager;
    private Employee $other; // de otro departamento

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-18');

        $nominas = Department::create(['name' => 'Nóminas']);
        $this->employee = $this->employeeWithRole('employee', $nominas);
        $this->manager = $this->employeeWithRole('manager', $nominas);
        $this->other = $this->employeeWithRole('employee', Department::create(['name' => 'Ventas']));

        $type = LeaveType::create(['name' => 'Vacaciones', 'requires_approval' => true, 'deducts_balance' => true]);
        // Hoy ausentes: el empleado y el de Ventas · pendiente: solo el de Ventas
        $this->leave($this->employee, $type, '2026-11-16', '2026-11-20', 'approved');
        $this->leave($this->other, $type, '2026-11-17', '2026-11-18', 'approved');
        $this->leave($this->other, $type, '2026-12-01', '2026-12-02', 'pending');

        // Octubre cerrado y noviembre en borrador: el empleado solo debe ver octubre
        foreach ([[10, 'closed', '1500.00'], [11, 'draft', '1600.00']] as [$month, $status, $net]) {
            $period = PayrollPeriod::create(['year' => 2026, 'month' => $month, 'status' => $status]);
            $period->payslips()->create([
                'employee_id' => $this->employee->id, 'gross' => $net, 'deductions' => '0.00', 'net' => $net, 'snapshot' => [],
            ]);
        }
    }

    private function employeeWithRole(string $role, Department $department): Employee
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->state(['role' => $role]),
            'department_id' => $department->id,
            'position_id' => Position::firstOrCreate(['name' => 'Técnico'])->id,
        ]);
        $employee->leaveBalances()->create(['year' => 2026, 'days_total' => 22, 'days_used' => 5]);

        return $employee;
    }

    private function leave(Employee $employee, LeaveType $type, string $start, string $end, string $status): void
    {
        $request = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $start, 'end_date' => $end, 'days' => 2,
        ]);
        $request->forceFill(['status' => $status])->save();
    }

    public function test_admin_ve_toda_la_empresa_y_el_ultimo_periodo(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/dashboard')->assertOk()->assertExactJson(['data' => [
            'active_employees' => 3,
            'pending_requests' => 1,
            'absent_today' => 2,
            'leave_days_available' => null,
            'last_payroll' => ['year' => 2026, 'month' => 11, 'status' => 'draft', 'payslips_count' => 1, 'net' => '1600.00'],
        ]]);
    }

    public function test_manager_solo_ve_su_departamento(): void
    {
        Sanctum::actingAs($this->manager->user);

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.active_employees', 2)
            ->assertJsonPath('data.pending_requests', 0)
            ->assertJsonPath('data.absent_today', 1)
            ->assertJsonPath('data.leave_days_available', 17)
            ->assertJsonPath('data.last_payroll', null);
    }

    public function test_empleado_ve_lo_suyo_y_solo_nominas_de_periodos_cerrados(): void
    {
        Sanctum::actingAs($this->employee->user);

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.active_employees', null)
            ->assertJsonPath('data.absent_today', 1)
            ->assertJsonPath('data.last_payroll.month', 10)
            ->assertJsonPath('data.last_payroll.net', '1500.00');
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
