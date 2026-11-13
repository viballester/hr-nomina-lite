<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Services\LeaveRequestService;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Catálogos fijos
        $positions = collect([
            'Técnico/a de nóminas',
            'Técnico/a de RR. HH.',
            'Desarrollador/a',
            'Administrativo/a',
            'Responsable de equipo',
        ])->map(fn ($name) => Position::create(['name' => $name]));

        $departments = collect([
            'Recursos Humanos',
            'Nóminas',
            'Tecnología',
            'Administración',
        ])->map(fn ($name) => Department::create(['name' => $name]));

        LeaveType::create([
            'name' => 'Vacaciones',
            'requires_approval' => true,
            'deducts_balance' => true,
        ]);

        LeaveType::create([
            'name' => 'Asuntos propios',
            'requires_approval' => true,
            'deducts_balance' => true,
        ]);

        LeaveType::create([
            'name' => 'Baja médica',
            'requires_approval' => false,
            'deducts_balance' => false,
        ]);

        // 2. Usuarios de demo, uno por rol (contraseña: password)
        $this->createEmployee($departments[0], $positions[4], [
            'name' => 'Admin Demo', 'email' => 'admin@demo.test', 'role' => 'admin',
        ]);

        $manager = $this->createEmployee($departments[0], $positions[4], [
            'name' => 'Manager Demo', 'email' => 'manager@demo.test', 'role' => 'manager',
        ]);
        $departments[0]->update(['manager_id' => $manager->id]);

        $empleado = $this->createEmployee($departments[0], $positions[1], [
            'name' => 'Empleado Demo', 'email' => 'empleado@demo.test', 'role' => 'employee',
        ]);

        // 3. Empleados inventados, repartidos al azar
        foreach (range(1, 20) as $i) {
            $this->createEmployee($departments->random(), $positions->random());
        }

        // 4. Ausencias demo, creadas con el servicio para que cumplan las reglas
        $service = app(LeaveRequestService::class);
        $vacaciones = LeaveType::where('name', 'Vacaciones')->first();
        $year = now()->year;

        $verano = $service->create($empleado, $vacaciones, Carbon::create($year, 8, 3), Carbon::create($year, 8, 14));
        $service->approve($verano, $manager);
        $service->create($empleado, $vacaciones, Carbon::create($year, 12, 14), Carbon::create($year, 12, 18));
    }

    // Crea usuario + empleado + contrato + saldo del año actual
    private function createEmployee(Department $department, Position $position, array $userData = []): Employee
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->create($userData)->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        Contract::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => $employee->hire_date,
        ]);

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'year' => now()->year,
            'days_total' => 22,
        ]);

        return $employee;
    }
}
