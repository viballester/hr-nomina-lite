<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PayrollSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollApiTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $role = 'employee'): Employee
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => Department::firstOrCreate(['name' => 'Nóminas'])->id,
            'position_id' => Position::firstOrCreate(['name' => 'Técnico'])->id,
            'hire_date' => '2020-01-01',
        ]);
        $employee->contracts()->create(['type' => 'indefinido', 'base_salary' => '2000.00', 'start_date' => '2020-01-01']);

        return $employee;
    }

    public function test_admin_crea_genera_y_cierra(): void
    {
        Sanctum::actingAs($this->employee('admin')->user);

        // El status enviado se ignora: siempre nace en borrador
        $id = $this->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 11, 'status' => 'closed'])
            ->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->postJson("/api/payroll-periods/$id/generate")->assertOk()->assertJsonPath('data.payslips_count', 1);
        $this->patchJson("/api/payroll-periods/$id/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson("/api/payroll-periods/$id/generate")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/payroll-periods')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_periodo_duplicado_o_invalido_devuelve_422(): void
    {
        Sanctum::actingAs($this->employee('admin')->user);
        PayrollPeriod::create(['year' => 2026, 'month' => 11]);

        $this->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 11])->assertJsonValidationErrors('month');
        $this->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 13])->assertJsonValidationErrors('month');
        $this->postJson('/api/payroll-periods', ['year' => 2027, 'month' => 11])->assertCreated();
    }

    public static function nonAdmins(): array
    {
        return [['employee'], ['manager']];
    }

    #[DataProvider('nonAdmins')]
    public function test_solo_admin_gestiona_periodos(string $role): void
    {
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 11]);
        Sanctum::actingAs($this->employee($role)->user);

        $this->getJson('/api/payroll-periods')->assertForbidden();
        $this->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 12])->assertForbidden();
        $this->postJson("/api/payroll-periods/$period->id/generate")->assertForbidden();
        $this->patchJson("/api/payroll-periods/$period->id/close")->assertForbidden();
    }

    #[DataProvider('nonAdmins')]
    public function test_cada_uno_ve_solo_sus_nominas_cerradas(string $role): void
    {
        $this->travelTo('2026-11-15');
        $me = $this->employee($role);
        $other = $this->employee();
        $this->seed(PayrollSeeder::class); // sep y oct cerrados, nov borrador
        Sanctum::actingAs($me->user);

        $this->getJson('/api/payslips')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.period.month', 10);

        $own = $me->payslips()->whereRelation('payrollPeriod', 'month', 10)->sole();
        $draft = $me->payslips()->whereRelation('payrollPeriod', 'month', 11)->sole();
        $foreign = $other->payslips()->whereRelation('payrollPeriod', 'month', 10)->sole();

        $this->getJson("/api/payslips/$own->id")->assertOk()
            ->assertJsonPath('data.net', $own->net)
            ->assertJsonCount(4, 'data.lines');
        $this->getJson("/api/payslips/$draft->id")->assertForbidden();
        $this->getJson("/api/payslips/$foreign->id")->assertForbidden(); // IDOR
    }

    public function test_admin_ve_todas_y_filtra_por_periodo(): void
    {
        $this->travelTo('2026-11-15');
        $admin = $this->employee('admin');
        $this->employee();
        $this->seed(PayrollSeeder::class);
        $this->seed(PayrollSeeder::class); // repetir no duplica
        Sanctum::actingAs($admin->user);

        $this->getJson('/api/payslips')->assertJsonPath('meta.total', 6);
        $draft = PayrollPeriod::where('status', 'draft')->sole();
        $this->getJson("/api/payslips?period_id=$draft->id")->assertJsonPath('meta.total', 2);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/payslips')->assertUnauthorized();
    }
}
