<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private Department $rrhh;
    private Department $nominas;
    private Position $position;

    // Se ejecuta antes de CADA test: datos mínimos comunes
    protected function setUp(): void
    {
        parent::setUp();
        $this->rrhh = Department::create(['name' => 'Recursos Humanos']);
        $this->nominas = Department::create(['name' => 'Nóminas']);
        $this->position = Position::create(['name' => 'Técnico']);
    }

    private function employeeWithRole(string $role, Department $department): Employee
    {
        return Employee::factory()->create([
            'user_id' => User::factory()->state(['role' => $role]),
            'department_id' => $department->id,
            'position_id' => $this->position->id,
        ]);
    }

    private function validEmployeeData(): array
    {
        return [
            'name' => 'Nueva Persona',
            'email' => 'nueva@demo.test',
            'department_id' => $this->rrhh->id,
            'position_id' => $this->position->id,
            'hire_date' => '2026-11-01',
        ];
    }

    // --- Autenticación ---

    public function test_login_devuelve_token_con_credenciales_correctas(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secreto123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_login_falla_con_contrasena_incorrecta(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'mala'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/employees')->assertUnauthorized();
    }

    // --- Autorización (Policy) ---

    public function test_admin_ve_todos_los_empleados(): void
    {
        $admin = $this->employeeWithRole('admin', $this->rrhh);
        $this->employeeWithRole('employee', $this->nominas);
        Sanctum::actingAs($admin->user);

        $this->getJson('/api/employees')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_manager_solo_ve_empleados_de_su_departamento(): void
    {
        $manager = $this->employeeWithRole('manager', $this->rrhh);
        $this->employeeWithRole('employee', $this->rrhh);
        $this->employeeWithRole('employee', $this->nominas);
        Sanctum::actingAs($manager->user);

        $this->getJson('/api/employees')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_employee_no_puede_listar_empleados(): void
    {
        $employee = $this->employeeWithRole('employee', $this->rrhh);
        Sanctum::actingAs($employee->user);

        $this->getJson('/api/employees')->assertForbidden();
    }

    public function test_employee_ve_su_ficha_pero_no_la_de_un_companero(): void
    {
        $yo = $this->employeeWithRole('employee', $this->rrhh);
        $companero = $this->employeeWithRole('employee', $this->rrhh);
        Sanctum::actingAs($yo->user);

        $this->getJson("/api/employees/{$yo->id}")->assertOk();
        $this->getJson("/api/employees/{$companero->id}")->assertForbidden(); // IDOR
    }

    public function test_manager_no_ve_fichas_de_otro_departamento(): void
    {
        $manager = $this->employeeWithRole('manager', $this->rrhh);
        $ajeno = $this->employeeWithRole('employee', $this->nominas);
        Sanctum::actingAs($manager->user);

        $this->getJson("/api/employees/{$ajeno->id}")->assertForbidden();
    }

    // --- Alta, validación y baja ---

    public function test_admin_da_de_alta_un_empleado(): void
    {
        Sanctum::actingAs($this->employeeWithRole('admin', $this->rrhh)->user);

        $this->postJson('/api/employees', $this->validEmployeeData())
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('users', ['email' => 'nueva@demo.test', 'role' => 'employee']);
    }

    public function test_manager_no_puede_dar_de_alta(): void
    {
        Sanctum::actingAs($this->employeeWithRole('manager', $this->rrhh)->user);

        $this->postJson('/api/employees', $this->validEmployeeData())->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nueva@demo.test']);
    }

    public function test_alta_rechaza_datos_invalidos(): void
    {
        Sanctum::actingAs($this->employeeWithRole('admin', $this->rrhh)->user);

        $this->postJson('/api/employees', ['email' => 'no-es-email', 'department_id' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'department_id', 'position_id', 'hire_date']);
    }

    public function test_baja_es_logica_y_conserva_el_empleado(): void
    {
        Sanctum::actingAs($this->employeeWithRole('admin', $this->rrhh)->user);
        $empleado = $this->employeeWithRole('employee', $this->rrhh);

        $this->deleteJson("/api/employees/{$empleado->id}")->assertNoContent();

        $this->assertDatabaseHas('employees', ['id' => $empleado->id, 'status' => 'inactive']);
    }
}
