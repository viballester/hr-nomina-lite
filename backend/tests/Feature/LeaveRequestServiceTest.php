<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\LeaveType;
use App\Services\LeaveRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private LeaveType $vacaciones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employee = Employee::factory()->create([
            'department_id' => Department::create(['name' => 'Nóminas'])->id,
            'position_id' => Position::create(['name' => 'Técnico'])->id,
        ]);
        $this->employee->leaveBalances()->create(['year' => 2026, 'days_total' => 5, 'days_used' => 0]);
        $this->vacaciones = LeaveType::create(['name' => 'Vacaciones', 'requires_approval' => true, 'deducts_balance' => true]);
    }

    private function crear(string $start, string $end)
    {
        return app(LeaveRequestService::class)->create($this->employee, $this->vacaciones, Carbon::parse($start), Carbon::parse($end));
    }

    public function test_crea_solicitud_pendiente_con_dias_laborables(): void
    {
        $request = $this->crear('2026-11-16', '2026-11-20');

        $this->assertSame('pending', $request->status);
        $this->assertSame(5, $request->days);
    }

    public function test_rechaza_solo_fin_de_semana(): void
    {
        $this->expectException(ValidationException::class);
        $this->crear('2026-11-21', '2026-11-22');
    }

    public function test_rechaza_solape(): void
    {
        $this->crear('2026-11-16', '2026-11-17');
        $this->expectException(ValidationException::class);
        $this->crear('2026-11-17', '2026-11-18');
    }

    public function test_pendientes_reservan_saldo(): void
    {
        $this->crear('2026-11-16', '2026-11-18'); // 3 días, quedan 2
        $this->expectException(ValidationException::class);
        $this->crear('2026-11-23', '2026-11-25'); // pide 3
    }
}