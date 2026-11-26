<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Http::fake() sustituye las llamadas salientes: no hace falta que el
 * pdf-service esté levantado y podemos simular respuestas y caídas.
 */
class PayslipPdfTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_URL = 'http://pdf.test/payslips/pdf';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pdf.url' => 'http://pdf.test', 'services.pdf.token' => 'token-test']);
    }

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

    private function payslipOf(Employee $employee, bool $closed = true): Payslip
    {
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 10]);
        $service = app(PayrollService::class);
        $service->generate($period);
        if ($closed) {
            $service->close($period);
        }

        return Payslip::where('employee_id', $employee->id)->firstOrFail();
    }

    public function test_el_empleado_descarga_su_nomina_cerrada(): void
    {
        Http::fake([self::PDF_URL => Http::response('%PDF-1.3 falso', 200, ['Content-Type' => 'application/pdf'])]);
        $me = $this->employee();
        $payslip = $this->payslipOf($me);
        Sanctum::actingAs($me->user);

        $response = $this->get("/api/payslips/{$payslip->id}/pdf")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload("nomina-2026-10-{$payslip->id}.pdf");
        $this->assertSame('%PDF-1.3 falso', $response->getContent());

        // Contrato con Node: token, datos del snapshot e importes como texto (nunca float)
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer token-test')
            && $request['employee'] === $me->user->name
            && $request['month'] === 10
            && $request['gross'] === $payslip->gross
            && is_string($request['lines'][0]['amount']));
    }

    public function test_no_se_llama_a_node_si_no_tiene_permiso(): void
    {
        Http::fake();
        $owner = $this->employee();
        $payslip = $this->payslipOf($owner, closed: false);

        // Su propia nómina, pero aún en borrador
        Sanctum::actingAs($owner->user);
        $this->getJson("/api/payslips/{$payslip->id}/pdf")->assertForbidden();

        // El manager tampoco ve nóminas ajenas (salario sensible)
        Sanctum::actingAs($this->employee('manager')->user);
        $this->getJson("/api/payslips/{$payslip->id}/pdf")->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_sin_autenticar_devuelve_401(): void
    {
        $payslip = $this->payslipOf($this->employee());

        $this->getJson("/api/payslips/{$payslip->id}/pdf")->assertUnauthorized();
    }

    public function test_devuelve_503_si_el_servicio_no_responde(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $me = $this->employee();
        $payslip = $this->payslipOf($me);
        Sanctum::actingAs($me->user);

        $this->getJson("/api/payslips/{$payslip->id}/pdf")
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El servicio de PDF no está disponible. Inténtalo más tarde.']);
    }

    public function test_devuelve_503_si_el_servicio_responde_con_error(): void
    {
        Http::fake([self::PDF_URL => Http::response(['message' => 'Error interno'], 500)]);
        $me = $this->employee();
        $payslip = $this->payslipOf($me);
        Sanctum::actingAs($me->user);

        $this->getJson("/api/payslips/{$payslip->id}/pdf")->assertStatus(503);
    }
}
