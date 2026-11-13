<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Services\LeaveRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private LeaveType $vacaciones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employee = $this->newEmployee();
        $this->vacaciones = LeaveType::create(['name' => 'Vacaciones', 'requires_approval' => true, 'deducts_balance' => true]);
    }

    private function newEmployee(): Employee
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory(),
            'department_id' => Department::firstOrCreate(['name' => 'Nóminas'])->id,
            'position_id' => Position::firstOrCreate(['name' => 'Técnico'])->id,
        ]);
        $employee->leaveBalances()->create(['year' => 2026, 'days_total' => 22, 'days_used' => 0]);

        return $employee;
    }

    public function test_empleado_crea_solicitud(): void
    {
        Sanctum::actingAs($this->employee->user);

        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $this->vacaciones->id,
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-20',
        ])
            ->assertCreated()
            ->assertJsonPath('data.days', 5)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_fechas_invertidas_devuelve_422(): void
    {
        Sanctum::actingAs($this->employee->user);

        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $this->vacaciones->id,
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-16',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_listado_solo_muestra_las_propias(): void
    {
        $service = app(LeaveRequestService::class);
        $service->create($this->employee, $this->vacaciones, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-17'));
        $service->create($this->newEmployee(), $this->vacaciones, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-17'));

        Sanctum::actingAs($this->employee->user);

        $this->getJson('/api/leave-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/leave-requests')->assertUnauthorized();
    }
}
