<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Services\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveCancelAndListApiTest extends TestCase
{
    use RefreshDatabase;

    private Department $nominas;
    private Employee $employee;
    private LeaveType $vacaciones;

    protected function setUp(): void
    {
        parent::setUp();
        // Fecha fija: las reglas de "ya ha empezado" dependen de today()
        $this->travelTo('2026-11-13');

        $this->nominas = Department::create(['name' => 'Nóminas']);
        $this->employee = $this->employeeWithRole('employee', $this->nominas);
        $this->vacaciones = LeaveType::create(['name' => 'Vacaciones', 'requires_approval' => true, 'deducts_balance' => true]);
    }

    private function employeeWithRole(string $role, Department $department): Employee
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->state(['role' => $role]),
            'department_id' => $department->id,
            'position_id' => Position::firstOrCreate(['name' => 'Técnico'])->id,
        ]);
        $employee->leaveBalances()->create(['year' => 2026, 'days_total' => 22, 'days_used' => 0]);

        return $employee;
    }

    private function request(Employee $employee, string $start = '2026-11-16', string $end = '2026-11-20'): LeaveRequest
    {
        return app(LeaveRequestService::class)->create($employee, $this->vacaciones, now()->parse($start), now()->parse($end));
    }

    private function daysUsed(): int
    {
        return $this->employee->leaveBalances()->where('year', 2026)->value('days_used');
    }

    // --- Cancelar ---

    public function test_cancela_pendiente(): void
    {
        $leave = $this->request($this->employee);
        Sanctum::actingAs($this->employee->user);

        $this->patchJson("/api/leave-requests/{$leave->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancelar_aprobada_devuelve_saldo(): void
    {
        $leave = $this->request($this->employee);
        $manager = $this->employeeWithRole('manager', $this->nominas);
        app(LeaveRequestService::class)->approve($leave, $manager);
        $this->assertSame(5, $this->daysUsed());

        Sanctum::actingAs($this->employee->user);
        $this->patchJson("/api/leave-requests/{$leave->id}/cancel")->assertOk();

        $this->assertSame(0, $this->daysUsed());
    }

    public function test_no_cancela_si_ya_ha_empezado(): void
    {
        $leave = $this->request($this->employee);
        $this->travelTo('2026-11-16');
        Sanctum::actingAs($this->employee->user);

        $this->patchJson("/api/leave-requests/{$leave->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_no_cancela_solicitud_ajena(): void
    {
        $leave = $this->request($this->employee);
        Sanctum::actingAs($this->employeeWithRole('employee', $this->nominas)->user);

        $this->patchJson("/api/leave-requests/{$leave->id}/cancel")->assertForbidden();
    }

    // --- Listado por rol ---

    public function test_manager_ve_solo_su_departamento(): void
    {
        $this->request($this->employee);
        $this->request($this->employeeWithRole('employee', Department::create(['name' => 'RRHH'])));
        Sanctum::actingAs($this->employeeWithRole('manager', $this->nominas)->user);

        $this->getJson('/api/leave-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee', $this->employee->user->name);
    }

    public function test_admin_ve_todas_y_filtra_por_estado(): void
    {
        $this->request($this->employee);
        $leave = $this->request($this->employeeWithRole('employee', Department::create(['name' => 'RRHH'])));
        app(LeaveRequestService::class)->cancel($leave);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/leave-requests')->assertJsonCount(2, 'data');
        $this->getJson('/api/leave-requests?status=pending')->assertJsonCount(1, 'data');
    }

    public function test_rol_desconocido_no_ve_el_listado(): void
    {
        $this->request($this->employee);
        $this->employee->user->forceFill(['role' => 'Manager'])->save();
        Sanctum::actingAs($this->employee->user);

        $this->getJson('/api/leave-requests')->assertForbidden();
    }

    // --- Saldo ---

    public function test_saldo_descuenta_usados_y_pendientes(): void
    {
        $this->request($this->employee, '2026-11-16', '2026-11-17');
        $leave = $this->request($this->employee, '2026-11-23', '2026-11-25');
        app(LeaveRequestService::class)->approve($leave, $this->employeeWithRole('manager', $this->nominas));
        Sanctum::actingAs($this->employee->user);

        $this->getJson('/api/leave-balance')
            ->assertOk()
            ->assertExactJson(['data' => ['days_total' => 22, 'days_used' => 3, 'days_pending' => 2, 'days_available' => 17]]);
    }
}
