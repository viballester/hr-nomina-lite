<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Services\LeaveRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private Department $nominas;
    private Employee $employee;
    private LeaveRequest $leaveRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nominas = Department::create(['name' => 'Nóminas']);
        $this->employee = $this->employeeWithRole('employee', $this->nominas);

        $vacaciones = LeaveType::create(['name' => 'Vacaciones', 'requires_approval' => true, 'deducts_balance' => true]);
        $this->leaveRequest = app(LeaveRequestService::class)
            ->create($this->employee, $vacaciones, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-20'));
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

    private function daysUsed(): int
    {
        return $this->employee->leaveBalances()->where('year', 2026)->value('days_used');
    }

    public function test_manager_del_departamento_aprueba_y_descuenta_saldo(): void
    {
        Sanctum::actingAs($this->employeeWithRole('manager', $this->nominas)->user);

        $this->patchJson("/api/leave-requests/{$this->leaveRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(5, $this->daysUsed());
    }

    public function test_rechazar_exige_comentario_y_no_descuenta_saldo(): void
    {
        Sanctum::actingAs($this->employeeWithRole('manager', $this->nominas)->user);
        $url = "/api/leave-requests/{$this->leaveRequest->id}/reject";

        $this->patchJson($url)->assertUnprocessable()->assertJsonValidationErrors('comment');

        $this->patchJson($url, ['comment' => 'Cierre de nóminas'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.review_comment', 'Cierre de nóminas');

        $this->assertSame(0, $this->daysUsed());
    }

    public function test_no_se_puede_aprobar_dos_veces(): void
    {
        Sanctum::actingAs($this->employeeWithRole('manager', $this->nominas)->user);
        $url = "/api/leave-requests/{$this->leaveRequest->id}/approve";

        $this->patchJson($url)->assertOk();
        $this->patchJson($url)->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(5, $this->daysUsed());
    }

    public function test_manager_de_otro_departamento_recibe_403(): void
    {
        $otro = Department::create(['name' => 'Recursos Humanos']);
        Sanctum::actingAs($this->employeeWithRole('manager', $otro)->user);

        $this->patchJson("/api/leave-requests/{$this->leaveRequest->id}/approve")->assertForbidden();
    }

    public function test_empleado_no_puede_aprobar(): void
    {
        Sanctum::actingAs($this->employeeWithRole('employee', $this->nominas)->user);

        $this->patchJson("/api/leave-requests/{$this->leaveRequest->id}/approve")->assertForbidden();
    }

    public function test_ni_el_admin_se_aprueba_a_si_mismo(): void
    {
        $this->employee->user->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($this->employee->user);

        $this->patchJson("/api/leave-requests/{$this->leaveRequest->id}/approve")->assertForbidden();
    }

    public function test_tipo_sin_aprobacion_se_autoaprueba(): void
    {
        $propios = LeaveType::create(['name' => 'Asuntos propios', 'requires_approval' => false, 'deducts_balance' => true]);
        Sanctum::actingAs($this->employee->user);

        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $propios->id,
            'start_date' => '2026-11-23',
            'end_date' => '2026-11-23',
        ])->assertCreated()->assertJsonPath('data.status', 'approved');

        $this->assertSame(1, $this->daysUsed());
    }
}
