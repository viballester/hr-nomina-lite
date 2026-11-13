<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepartmentAndPositionApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    // Los dos recursos comparten las mismas reglas: solo cambia la URL,
    // el modelo y la relación por la que comprobamos que tiene empleados.
    public static function recursos(): array
    {
        return [
            'departments' => ['departments', Department::class, 'department_id'],
            'positions' => ['positions', Position::class, 'position_id'],
        ];
    }

    #[DataProvider('recursos')]
    public function test_cualquier_usuario_autenticado_puede_listar(string $uri, string $model, string $foreignKey): void
    {
        $model::create(['name' => 'Recursos Humanos']);
        Sanctum::actingAs($this->userWithRole('employee'));

        $this->getJson("/api/{$uri}")->assertOk()->assertJsonCount(1, 'data');
    }

    #[DataProvider('recursos')]
    public function test_employee_no_puede_crear(string $uri, string $model, string $foreignKey): void
    {
        Sanctum::actingAs($this->userWithRole('employee'));

        $this->postJson("/api/{$uri}", ['name' => 'Calidad'])->assertForbidden();

        $this->assertDatabaseMissing((new $model)->getTable(), ['name' => 'Calidad']);
    }

    #[DataProvider('recursos')]
    public function test_manager_no_puede_crear(string $uri, string $model, string $foreignKey): void
    {
        Sanctum::actingAs($this->userWithRole('manager'));

        $this->postJson("/api/{$uri}", ['name' => 'Calidad'])->assertForbidden();
    }

    #[DataProvider('recursos')]
    public function test_admin_crea_un_registro(string $uri, string $model, string $foreignKey): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson("/api/{$uri}", ['name' => 'Calidad'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Calidad');
    }

    #[DataProvider('recursos')]
    public function test_no_admite_nombres_duplicados(string $uri, string $model, string $foreignKey): void
    {
        $model::create(['name' => 'Calidad']);
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson("/api/{$uri}", ['name' => 'Calidad'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[DataProvider('recursos')]
    public function test_admin_edita_un_registro_con_su_propio_nombre(string $uri, string $model, string $foreignKey): void
    {
        $registro = $model::create(['name' => 'Calidad']);
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->patchJson("/api/{$uri}/{$registro->id}", ['name' => 'Calidad'])->assertOk();
    }

    #[DataProvider('recursos')]
    public function test_admin_no_puede_borrar_un_registro_con_empleados(string $uri, string $model, string $foreignKey): void
    {
        $registro = $model::create(['name' => 'Calidad']);
        $otraFk = $model === Department::class ? 'position_id' : 'department_id';
        $otroModelo = $model === Department::class ? Position::class : Department::class;

        Employee::factory()->create([
            $foreignKey => $registro->id,
            $otraFk => $otroModelo::create(['name' => 'Auxiliar'])->id,
        ]);
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->deleteJson("/api/{$uri}/{$registro->id}")->assertStatus(409);

        $this->assertDatabaseHas((new $model)->getTable(), ['id' => $registro->id]);
    }

    #[DataProvider('recursos')]
    public function test_admin_borra_un_registro_sin_empleados(string $uri, string $model, string $foreignKey): void
    {
        $registro = $model::create(['name' => 'Calidad']);
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->deleteJson("/api/{$uri}/{$registro->id}")->assertNoContent();

        $this->assertDatabaseMissing((new $model)->getTable(), ['id' => $registro->id]);
    }
}
