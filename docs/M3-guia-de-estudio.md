# Guía de estudio del módulo M3: ausencias

Apuntes de todo lo trabajado en el módulo M3 de *HR Nómina Lite*, escritos para estudiar y para consultar más adelante.

**Fecha de realización:** 13 de noviembre de 2026
**Repositorio:** [viballester/hr-nomina-lite](https://github.com/viballester/hr-nomina-lite) · Pull Request #5

## Contenido

1. [Visión general](#1-visión-general)
2. [La capa de servicio](#2-la-capa-de-servicio)
3. [Días laborables y test unitario](#3-días-laborables-y-test-unitario)
4. [Crear una solicitud: las reglas de negocio](#4-crear-una-solicitud-las-reglas-de-negocio)
5. [Errores de negocio con `ValidationException`](#5-errores-de-negocio-con-validationexception)
6. [La capa HTTP: Form Request, controlador y Resource](#6-la-capa-http-form-request-controlador-y-resource)
7. [Aprobar y rechazar: transacciones y bloqueos](#7-aprobar-y-rechazar-transacciones-y-bloqueos)
8. [Autorización: `LeaveRequestPolicy`](#8-autorización-leaverequestpolicy)
9. [Cancelar: compensar un efecto](#9-cancelar-compensar-un-efecto)
10. [Listado por rol, cerrado por defecto](#10-listado-por-rol-cerrado-por-defecto)
11. [Saldo: una sola fuente de verdad](#11-saldo-una-sola-fuente-de-verdad)
12. [Datos demo con el servicio](#12-datos-demo-con-el-servicio)
13. [Tests del módulo](#13-tests-del-módulo)
14. [Las rutas del módulo](#14-las-rutas-del-módulo)
15. [Problemas encontrados y cómo se resolvieron](#15-problemas-encontrados-y-cómo-se-resolvieron)
16. [Chuleta de comandos](#16-chuleta-de-comandos)
17. [Preguntas de entrevista](#17-preguntas-de-entrevista)
18. [Glosario](#18-glosario)

---

## 1. Visión general

### Objetivo del módulo

M3 añade la primera funcionalidad **con reglas de negocio de verdad**. Hasta M2, la API guardaba y devolvía datos; ahora tiene que **decidir**. Por ejemplo: ¿le quedan días a este empleado?, ¿se solapa con otra ausencia?, ¿quién puede aprobarla?

El flujo completo:

```
          crear                 aprobar
empleado ───────► pending ───────────────► approved ──┐
                    │   rechazar                       │ cancelar
                    ├──────────► rejected              │ (antes de empezar)
                    │   cancelar                       ▼
                    └──────────────────────────► cancelled
```

Los tipos de ausencia que no requieren aprobación, como "Asuntos propios", nacen directamente como `approved`.

### Criterio de finalización

M3 se daba por terminado cuando se cumplieran tres condiciones. La primera, que un empleado pudiera solicitar, cancelar y consultar su saldo. La segunda, que un manager pudiera aprobar o rechazar las solicitudes de su departamento. La tercera, que todas las reglas (saldo, solapes, estados y permisos) estuvieran cubiertas por tests. Se cumplió el 13 de noviembre, con 58 tests en verde.

### Resultado

| Elemento | Cantidad |
|---|---|
| Servicios nuevos | 1 (`LeaveRequestService`) |
| Controladores nuevos | 1 (`LeaveRequestController`, 6 acciones) |
| Form Requests / Resources / Policies | 1 / 1 / 1 |
| Rutas nuevas | 6 |
| Tests nuevos | 28 (5 unitarios + 23 de integración) |
| Tests totales del proyecto | 58 (123 assertions) |

### Archivos creados o modificados

| Ruta | Contenido |
|---|---|
| `backend/app/Services/LeaveRequestService.php` | Todas las reglas de ausencias |
| `backend/app/Http/Controllers/Api/LeaveRequestController.php` | `index`, `store`, `approve`, `reject`, `cancel`, `balance` |
| `backend/app/Http/Requests/StoreLeaveRequestRequest.php` | Validación de formato al crear |
| `backend/app/Http/Resources/LeaveRequestResource.php` | Forma del JSON |
| `backend/app/Policies/LeaveRequestPolicy.php` | `review` y `cancel` |
| `backend/routes/api.php` | Rutas de ausencias y saldo |
| `backend/database/seeders/DatabaseSeeder.php` | Ausencias demo |
| `backend/tests/Unit/LeaveRequestServiceTest.php` | Días laborables |
| `backend/tests/Feature/LeaveRequestServiceTest.php`, `LeaveRequestApiTest.php`, `LeaveReviewApiTest.php`, `LeaveCancelAndListApiTest.php` | Integración |

---

## 2. La capa de servicio

### 2.1 Qué problema resuelve

En M2 casi toda la lógica cabía en el controlador, porque se limitaba a validar, guardar y devolver. En M3 crear una ausencia implica **cuatro comprobaciones y dos tablas**. Si eso va en el controlador:

- Solo se puede probar a través de HTTP, con tests más lentos y más difíciles de escribir.
- No se puede reutilizar. El seeder (sección 12) necesitaba crear ausencias con las mismas reglas.
- El controlador tendría dos responsabilidades: traducir HTTP **y** decidir reglas de negocio.

### 2.2 El reparto de responsabilidades

| Capa | Pregunta que responde | Ejemplo en M3 |
|---|---|---|
| Form Request | ¿El **formato** de la entrada es válido? | `end_date` es una fecha ≥ `start_date` |
| Policy | ¿**Este usuario** puede hacer esto con **este objeto**? | El manager es del mismo departamento |
| Controlador | ¿Cómo traduzco HTTP en una llamada y la respuesta en JSON? | `store()` llama a `$service->create()` |
| Servicio | ¿Se cumplen las **reglas de negocio**? | Hay saldo y no hay solapes |
| Resource | ¿Qué campos salen en el JSON? | `leave_type` como nombre, fechas `Y-m-d` |

**Principio SOLID asociado:** la **S** (responsabilidad única). Cada clase tiene un único motivo para cambiar.

### 2.3 Inyección de dependencias

El controlador no crea el servicio con `new`, sino que lo **declara como parámetro**:

```php
public function store(StoreLeaveRequestRequest $request, LeaveRequestService $service)
```

Laravel lee el tipo, construye el objeto y lo pasa. Es el mismo mecanismo por el que ya recibías `$request`. Ventaja: el controlador no decide *cómo* se construye el servicio (la **D** de SOLID, inversión de dependencias), y en un test se podría sustituir por un doble falso.

Fuera de un controlador, como en un test o en el seeder, se obtiene con `app(LeaveRequestService::class)`.

---

## 3. Días laborables y test unitario

### 3.1 El cálculo

```php
public function countWorkingDays(CarbonInterface $start, CarbonInterface $end): int
{
    $days = 0;
    $current = $start->copy();

    while ($current->lte($end)) {
        if (!$current->isWeekend()) {
            $days++;
        }
        $current->addDay();
    }
    return $days;
}
```

- **Carbon** es la librería de fechas de Laravel. Los casts `'date'` del modelo ya devuelven objetos Carbon.
- **`copy()` es obligatorio:** `addDay()` modifica el objeto. Sin la copia, el bucle cambiaría la `$start` que te pasaron y el que llama vería otra fecha.
- `lte()` significa *less than or equal* (≤).
- Si `$end < $start`, devuelve **0, no un número negativo**, porque el bucle no llega a ejecutarse. Ese 0 parece un resultado válido. Por eso el rango invertido se rechaza **antes**, en el Form Request (sección 6).

### 3.2 Por qué es un test **unitario**

Es una función pura: recibe dos fechas y devuelve un número, sin base de datos ni HTTP. Por eso su test va en `tests/Unit` y hereda de `PHPUnit\Framework\TestCase`, que no arranca Laravel. Es mucho más rápido que un test de integración.

Los 5 casos (un lunes, una semana de lunes a viernes, un fin de semana, de viernes a lunes, dos semanas completas) usan `#[DataProvider]`, igual que en M2.

---

## 4. Crear una solicitud: las reglas de negocio

`create()` aplica las reglas de la **más barata a la más cara**: primero el cálculo en memoria y después las consultas a la base de datos.

| # | Regla | Por qué |
|---|---|---|
| 0 | No puede cruzar de año | El saldo es anual (`leave_balances.year`). *Límite conocido.* |
| 1 | Debe tener al menos 1 día laborable | Una solicitud de solo fin de semana no tiene sentido |
| 2 | No puede solaparse con otra `pending` o `approved` del mismo empleado | Las `rejected` y `cancelled` ya no ocupan esas fechas |
| 3 | Debe haber saldo (solo si el tipo `deducts_balance`) | La baja médica no consume vacaciones |
| 4 | Se crea `pending`, o `approved` si el tipo no requiere aprobación | Autoaprobación |

### 4.1 La condición de solape

Dos intervalos [A, B] y [C, D] se solapan si y solo si **A ≤ D y B ≥ C**.

```php
$employee->leaveRequests()
    ->whereIn('status', ['pending', 'approved'])
    ->where('start_date', '<=', $end)
    ->where('end_date', '>=', $start)
    ->exists();
```

Compruébalo en papel. Del 16 al 20 y del 20 al 23 se solapan, porque comparten el día 20. Del 16 al 19 y del 20 al 23 no se solapan.

### 4.2 Los pendientes reservan saldo

```
disponible = days_total - days_used - (suma de days de sus solicitudes pending del año)
```

Si no restáramos las pendientes, un empleado con 5 días podría pedir dos veces 5 días: ambas pasarían la validación y el manager acabaría aprobando 10. El test `test_pendientes_reservan_saldo` existe para demostrarlo.

### 4.3 `status` fuera de Fillable

Igual que en M1, `status` no se puede asignar en masa. Si estuviera en Fillable, un cliente podría enviar `"status": "approved"` en el POST y autoaprobarse. Por eso se asigna aparte:

```php
$leaveRequest = $employee->leaveRequests()->make([...]); // construye sin guardar
$leaveRequest->status = $type->requires_approval ? 'pending' : 'approved';
$leaveRequest->save();                                    // un único INSERT
```

`make()` en vez de `create()`: `create()` hace un INSERT inmediato, y el `save()` posterior añadiría un UPDATE. Con `make()` hay una sola consulta.

### 4.4 La autoaprobación, en transacción

Si el tipo no requiere aprobación pero sí descuenta saldo, hay **dos escrituras**: el INSERT de la solicitud y el `increment` del saldo. Por eso van juntas dentro de `DB::transaction` (sección 7).

---

## 5. Errores de negocio con `ValidationException`

```php
throw ValidationException::withMessages(['leave_type_id' => 'Saldo insuficiente.']);
```

Laravel convierte esta excepción en un **422** con exactamente el mismo formato que los errores del Form Request:

```json
{ "message": "Saldo insuficiente.", "errors": { "leave_type_id": ["Saldo insuficiente."] } }
```

El frontend recibe todos los errores igual, sin importar la capa que los genere. Lo comprobamos con curl: el rango invertido (Form Request) y el solape (servicio) devolvían la misma forma.

**Alternativa descartada:** excepciones propias como `InsufficientBalanceException`. Son más expresivas, pero exigen registrar en `bootstrap/app.php` cómo se transforman en respuesta HTTP. No compensa en el MVP (DECISIONS D-013).

**¿422 o 403?** Las reglas de **negocio** (estado, fechas, saldo) dan 422: la petición es de alguien autorizado, pero los datos no cumplen las reglas. Los **permisos** dan 403 (sección 8).

---

## 6. La capa HTTP: Form Request, controlador y Resource

### 6.1 `StoreLeaveRequestRequest`

```php
public function authorize(): bool
{
    return $this->user()->employee !== null;   // solo quien tiene ficha de empleado
}

public function rules(): array
{
    return [
        'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
        'start_date'    => ['required', 'date'],
        'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
    ];
}
```

`after_or_equal:start_date` es la respuesta al ejercicio de la tarea 1: el rango invertido se rechaza en la **frontera de entrada**.

### 6.2 El controlador no contiene reglas

```php
public function store(StoreLeaveRequestRequest $request, LeaveRequestService $service)
{
    $data = $request->validated();

    $leaveRequest = $service->create(
        $request->user()->employee,
        LeaveType::findOrFail($data['leave_type_id']),
        Carbon::parse($data['start_date']),
        Carbon::parse($data['end_date']),
    );

    return (new LeaveRequestResource($leaveRequest->load('leaveType')))
        ->response()
        ->setStatusCode(201);
}
```

Fíjate en que el `employee` sale **del usuario autenticado**, no del cuerpo de la petición. Un empleado no puede crear ausencias a nombre de otro.

### 6.3 `LeaveRequestResource`

- `$this->start_date->toDateString()` da `"2026-11-16"` en lugar de `"2026-11-16T00:00:00.000000Z"`.
- `'employee' => $this->whenLoaded('employee', fn () => $this->employee->user->name)`: solo incluye el nombre si la relación se cargó con `with()`. Así se evita el problema **N+1** (una consulta extra por cada fila del listado).

---

## 7. Aprobar y rechazar: transacciones y bloqueos

### 7.1 Transacción

Aprobar cambia **dos tablas**: el `status` de la solicitud y el `days_used` del saldo.

```php
return DB::transaction(function () use ($leaveRequest, $reviewer) {
    // ... si algo lanza una excepción aquí dentro, se deshace TODO
});
```

O se confirman todos los cambios (*commit*) o ninguno (*rollback*). Sin la transacción, un fallo a mitad de proceso podría dejar la solicitud `approved` sin haber descontado los días.

### 7.2 Condición de carrera y `lockForUpdate`

**El problema:** dos managers pulsan "Aprobar" sobre la misma solicitud en el mismo milisegundo.

```
Petición A: lee status = pending ✓        Petición B: lee status = pending ✓
Petición A: days_used += 5                Petición B: days_used += 5     ← ¡10 días descontados!
```

**La solución:** dentro de la transacción, se vuelve a leer la fila **bloqueándola**:

```php
private function lockPending(LeaveRequest $request): LeaveRequest
{
    $locked = LeaveRequest::query()
        ->whereKey($request->id)
        ->lockForUpdate()          // SELECT ... FOR UPDATE
        ->firstOrFail();

    if ($locked->status !== 'pending') {
        throw ValidationException::withMessages([
            'status' => 'Solo se pueden revisar solicitudes pendientes.',
        ]);
    }

    return $locked;
}
```

La petición B se queda **esperando** hasta que A termina su transacción. Cuando por fin lee la fila, ya ve `approved` y recibe un 422.

**El saldo también se bloquea.** Una aprobación y una autoaprobación del mismo empleado pueden coincidir sobre la misma fila de `leave_balances`:

```php
$leaveRequest->employee->leaveBalances()
    ->where('year', $leaveRequest->start_date->year)
    ->lockForUpdate()
    ->firstOrFail()
    ->increment('days_used', $leaveRequest->days);
```

**Regla para recordar:** *si un cambio toca más de una fila o tabla, va en una transacción; si además hay que leer un dato para decidir antes de escribir, esa fila se bloquea.*

**Limitación de los tests:** SQLite ignora `lockForUpdate`. Los tests comprueban **qué** pasa (por ejemplo, que no se pueda aprobar dos veces seguidas), pero no la concurrencia real, que solo funciona en MySQL. Por eso en la revisión de código hay que mirar también lo que los tests no pueden ver.

### 7.3 DRY: `lockPending()`

`approve()` y `reject()` comparten los mismos pasos (bloquear y comprobar que está `pending`). Extraerlos a un método privado evita que una copia se corrija y la otra no.

### 7.4 Rechazar

Igual que aprobar, pero **sin tocar el saldo**: la solicitud pendiente solo *reservaba* días, no los gastaba. Además, el rechazo **exige un comentario**, para que el empleado sepa el motivo:

```php
$data = $request->validate(['comment' => ['required', 'string', 'max:500']]);
```

---

## 8. Autorización: `LeaveRequestPolicy`

### 8.1 `review`: el orden importa

```php
public function review(User $user, LeaveRequest $leaveRequest): bool
{
    if ($leaveRequest->employee_id === $user->employee?->id) {
        return false; // nadie se aprueba a sí mismo, ni el admin
    }

    if ($user->role === 'admin') {
        return true;
    }

    return $user->role === 'manager'
        && $leaveRequest->employee->department_id === $user->employee?->department_id;
}
```

**Diferencia con M2:** en `EmployeePolicy`, `before()` devuelve `true` para el admin en cualquier caso. Aquí hay una regla que **ni el admin** puede saltarse. Si se usara `before()`, el admin podría aprobarse sus propias vacaciones. Por eso la comprobación de "es tu propia solicitud" va **antes** de la del admin, y no se usa `before()`.

Esto se conoce como **segregación de funciones**: quien solicita no puede ser quien aprueba. Es un requisito habitual en software de RR. HH. y nóminas.

### 8.2 `cancel`: solo el propietario

```php
public function cancel(User $user, LeaveRequest $leaveRequest): bool
{
    return $leaveRequest->employee_id === $user->employee?->id;
}
```

Las reglas de estado y fecha **no** van aquí, sino en el servicio. La Policy responde "¿es tuya?" (403 si no lo es) y el servicio responde "¿se puede cancelar ahora?" (422 si no se puede).

---

## 9. Cancelar: compensar un efecto

Cancelar una solicitud `approved` tiene que **deshacer** lo que hizo `approve()`: devolver los días al saldo. Es una **operación compensatoria**, así que necesita la misma transacción y el mismo bloqueo.

Reglas:
- Solo se pueden cancelar solicitudes `pending` o `approved`.
- Solo si **aún no han empezado** (`start_date > today()`). No tiene sentido cancelar unas vacaciones ya disfrutadas.
- Si estaba `approved` y el tipo descuenta saldo, se aplica `decrement('days_used', $days)`.

**Límite conocido (DECISIONS D-014):** la devolución mira el `deducts_balance` del tipo **en el momento de cancelar**. Si alguien cambiara ese valor entre la aprobación y la cancelación, se devolverían días que nunca se descontaron. Hoy no puede ocurrir, porque no hay ningún endpoint que modifique los tipos de ausencia.

---

## 10. Listado por rol, cerrado por defecto

### 10.1 Por qué la Policy no filtra listas (repaso de M2)

Una Policy responde sobre **un** objeto. Un listado tiene muchos, y comprobarlos uno a uno después de traerlos todos sería lento y rompería la paginación. El filtro va **en la consulta**.

| Rol | Ve |
|---|---|
| `employee` | sus solicitudes |
| `manager` | las de su departamento, incluidas las suyas |
| `admin` | todas |

### 10.2 Cerrado por defecto (*fail-closed*)

Primera versión, con `when()`:

```php
->when($user->role === 'employee', fn ($q) => ...)
->when($user->role === 'manager',  fn ($q) => ...)
```

**El fallo:** si el rol no es ninguno de los dos (una errata como `'Manager'` o un rol nuevo en el futuro), **no se aplica ningún filtro** y el usuario ve todo, igual que el admin. Este error lo detectó una revisión de seguridad automática.

Versión final:

```php
$query = match ($user->role) {
    'admin'    => LeaveRequest::query(),
    'manager'  => LeaveRequest::whereHas('employee', fn ($e) => $e->where('department_id', $user->employee->department_id)),
    'employee' => LeaveRequest::where('employee_id', $user->employee->id),
    default    => abort(403),
};
```

**Principio:** en seguridad, lo que no está explícitamente permitido está prohibido. Ante la duda, se niega el acceso. Lo comprueba `test_rol_desconocido_no_ve_el_listado`.

### 10.3 Filtro por estado

`?status=pending` le servirá al manager como "bandeja de pendientes" en el frontend (M6):

```php
->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
```

---

## 11. Saldo: una sola fuente de verdad

`GET /api/leave-balance` devuelve:

```json
{ "data": { "days_total": 22, "days_used": 10, "days_pending": 5, "days_available": 7 } }
```

El endpoint tiene que calcular **exactamente lo mismo** que `create()` usa para decidir si hay saldo. Si el cálculo estuviera escrito dos veces y algún día cambiara la regla, el empleado vería "te quedan 3 días" mientras la API le rechaza una solicitud de 3 días.

Solución: se **extrajo** el cálculo a `balanceSummary()` y se usa en los dos sitios.

```php
// En create():
$summary = $this->balanceSummary($employee, $start->year);
if (!$summary || $summary['days_available'] < $days) { ... }
```

**Esto es un refactor:** se cambia la estructura sin cambiar el comportamiento. La prueba de que lo era es que los 56 tests anteriores pasaron **sin modificar ninguno**. Para eso sirven los tests: te dejan reorganizar el código con la tranquilidad de no haber roto nada.

`(int) $pending`: `sum()` puede devolver un string en MySQL.

---

## 12. Datos demo con el servicio

```php
$service = app(LeaveRequestService::class);
$vacaciones = LeaveType::where('name', 'Vacaciones')->first();
$year = now()->year;

$verano = $service->create($empleado, $vacaciones, Carbon::create($year, 8, 3), Carbon::create($year, 8, 14));
$service->approve($verano, $manager);
$service->create($empleado, $vacaciones, Carbon::create($year, 12, 14), Carbon::create($year, 12, 18));
```

Crear los datos demo **a través del servicio**, y no con `LeaveRequest::create()`, garantiza que cumplen las mismas reglas que la API: hay saldo, no hay solapes y los días están bien contados. Así es imposible sembrar datos que la aplicación no podría haber generado.

Resultado para el Empleado Demo: 22 días en total, 10 usados (agosto), 5 pendientes (diciembre) y 7 disponibles.

---

## 13. Tests del módulo

### 13.1 Distribución

| Archivo | Tipo | Qué prueba |
|---|---|---|
| `Unit/LeaveRequestServiceTest` | Unitario | Días laborables (5 casos con `#[DataProvider]`) |
| `Feature/LeaveRequestServiceTest` | Integración (servicio) | Crear, fin de semana, solape, pendientes reservan saldo |
| `Feature/LeaveRequestApiTest` | Integración (HTTP) | 201, 422 con rango invertido, listado propio, 401 |
| `Feature/LeaveReviewApiTest` | Integración (HTTP) | Aprobar descuenta saldo, rechazar exige comentario y no descuenta, no se aprueba dos veces, 403 para otro departamento, para un empleado o para uno mismo (aunque sea admin), autoaprobación |
| `Feature/LeaveCancelAndListApiTest` | Integración (HTTP) | Cancelar pendiente, cancelar aprobada devuelve saldo, ya empezada → 422, ajena → 403, listado por rol, filtro `?status`, rol desconocido → 403, saldo exacto |

### 13.2 Congelar el reloj: `travelTo`

Las reglas de cancelación dependen de `today()`. Un test con fechas fijas funcionaría hoy y fallaría dentro de una semana. La solución:

```php
$this->travelTo('2026-11-13');   // a partir de aquí, now() y today() devuelven esa fecha
```

En `test_no_cancela_si_ya_ha_empezado`, el reloj se **adelanta** a `2026-11-16` para simular que la ausencia ya ha empezado.

### 13.3 Probar el servicio sin HTTP

`LeaveRequestServiceTest` llama directamente a `app(LeaveRequestService::class)->create(...)` y comprueba las excepciones con:

```php
$this->expectException(ValidationException::class);
```

Aquí se ve la ventaja de la capa de servicio (sección 2.1): las reglas se prueban sin construir peticiones HTTP.

---

## 14. Las rutas del módulo

```php
Route::get('/leave-balance', [LeaveRequestController::class, 'balance']);
Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'store']);
Route::patch('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
Route::patch('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);
```

| Método | Ruta | Quién | Respuestas |
|---|---|---|---|
| GET | `/api/leave-requests[?status=]` | todos (filtrado por rol) | 200, 403 |
| POST | `/api/leave-requests` | quien tiene ficha de empleado | 201, 422, 403 |
| PATCH | `/api/leave-requests/{id}/approve` | manager del dpto. o admin, nunca uno mismo | 200, 403, 422 |
| PATCH | `/api/leave-requests/{id}/reject` | ídem; exige `comment` | 200, 403, 422 |
| PATCH | `/api/leave-requests/{id}/cancel` | solo el propio empleado | 200, 403, 422 |
| GET | `/api/leave-balance` | quien tiene ficha de empleado | 200, 403, 404 |

**¿Por qué PATCH y no PUT?** Se modifica una parte del recurso (su estado), no se sustituye entero. **¿Por qué acciones en la URL (`/approve`) en lugar de `PATCH {status: "approved"}`?** Cada transición tiene permisos y efectos distintos. Una ruta por acción hace explícito qué se está haciendo y permite autorizar cada una por separado.

Todas van dentro del grupo `auth:sanctum` (401 sin token).

---

## 15. Problemas encontrados y cómo se resolvieron

| Problema | Causa | Solución |
|---|---|---|
| `NOT NULL constraint failed: employees.department_id` en los tests | `Employee::factory()` no crea departamento ni puesto | Crear `Department` y `Position` en el `setUp` y pasar sus ids, como en `EmployeeApiTest` |
| `UNIQUE constraint` al crear un segundo empleado en un test | `Department::create(['name' => 'Nóminas'])` dos veces | `firstOrCreate` |
| Dos consultas (INSERT + UPDATE) al crear una solicitud | `create()` seguido de `save()` para asignar `status` | `make()` + `save()`: un único INSERT. **Error repetido**: se volvió a `create()` en la tarea 4 |
| Autoaprobación sin transacción | Tres escrituras sueltas: si fallaba el `increment`, quedaba la solicitud aprobada sin descontar | Envolver el paso 4 de `create()` en `DB::transaction` |
| Falta `lockForUpdate` en el saldo | Se bloqueaba la solicitud pero no la fila de `leave_balances` | Añadir `->lockForUpdate()` antes de `firstOrFail()`. **Error repetido** en las tareas 4 y 5 |
| `Duplicate entry 'empleado@demo.test'` en el seeder | Se **añadió** una línea nueva con `$empleado = ...` en lugar de **modificar** la existente | Borrar la copia y poner `$empleado =` en la línea original (así conserva el id 3) |
| Un rol desconocido veía todas las ausencias | Filtro con `when()`, que por defecto no filtra nada (*fail-open*) | `match` con `default => abort(403)` (*fail-closed*). Detectado por una revisión de seguridad automática |

---

## 16. Chuleta de comandos

```bash
# Generar las piezas del módulo
docker compose exec app php artisan make:request StoreLeaveRequestRequest
docker compose exec app php artisan make:resource LeaveRequestResource
docker compose exec app php artisan make:controller Api/LeaveRequestController
docker compose exec app php artisan make:policy LeaveRequestPolicy --model=LeaveRequest

# Ver solo las rutas de ausencias
docker compose exec app php artisan route:list --path=leave

# Ejecutar solo los tests unitarios / un archivo concreto
docker compose exec app php artisan test --testsuite=Unit
docker compose exec app php artisan test --filter=LeaveRequestServiceTest

# Reconstruir la BD de desarrollo con las ausencias demo
docker compose exec app php artisan migrate:fresh --seed
```

---

## 17. Preguntas de entrevista

- [ ] Por qué las reglas de saldo y solape van en un servicio y no en el controlador.
- [ ] Qué es la inyección de dependencias y qué ventaja tiene recibir el servicio como parámetro.
- [ ] Cómo se detecta que dos intervalos de fechas se solapan (A ≤ D y B ≥ C).
- [ ] Por qué las solicitudes pendientes reservan saldo.
- [ ] Qué es una condición de carrera y cómo la evitan `DB::transaction` + `lockForUpdate`.
- [ ] Por qué los tests con SQLite no demuestran que el bloqueo funcione.
- [ ] Por qué "nadie se aprueba a sí mismo" va antes del permiso del admin y no se usa `before()`.
- [ ] Diferencia entre un 403 y un 422 en este módulo (con ejemplo de cada uno).
- [ ] Qué significa que un filtro esté "cerrado por defecto" y qué fallo tenía la primera versión del listado.
- [ ] Qué es un refactor y cómo demostraron los tests que `balanceSummary()` no cambió el comportamiento.
- [ ] Por qué los datos demo se crean a través del servicio.
- [ ] Por qué se usa `travelTo` en los tests de cancelación.

---

## 18. Glosario

| Término | Significado |
|---|---|
| **Capa de servicio** (*service layer*) | Clase que concentra las reglas de negocio de un área, separada del controlador y del modelo |
| **Inyección de dependencias** | El framework construye y entrega los objetos que una clase necesita, en vez de que la clase los cree con `new` |
| **Regla de negocio** | Restricción que viene del dominio (RR. HH.), no del formato de los datos: "no se puede gastar más saldo del que hay" |
| **Máquina de estados** | Conjunto de estados posibles y de transiciones permitidas entre ellos |
| **Condición de carrera** (*race condition*) | Error que aparece cuando dos operaciones simultáneas leen el mismo dato antes de que ninguna lo actualice |
| **`lockForUpdate`** | `SELECT ... FOR UPDATE`: bloquea la fila hasta el final de la transacción; otras transacciones que quieran bloquearla esperan |
| **Operación compensatoria** | La que deshace el efecto de otra anterior (cancelar devuelve lo que aprobar descontó) |
| **Segregación de funciones** | Quien solicita una operación no puede ser quien la aprueba |
| **Fail-closed / cerrado por defecto** | Ante un caso no previsto, se niega el acceso en vez de concederlo |
| **Refactor** | Cambiar la estructura del código sin cambiar su comportamiento observable |
| **Una sola fuente de verdad** | Cada dato o cálculo se define en un único sitio y el resto lo reutiliza |
| **`make()` vs `create()`** | `make()` construye el modelo sin guardarlo; `create()` lo guarda en el acto |
| **`whenLoaded`** | En un Resource, incluye un campo solo si la relación ya se cargó (evita N+1) |
| **`travelTo`** | Helper de tests de Laravel que congela o mueve el reloj de la aplicación |
| **Test unitario vs de integración** | El unitario prueba una pieza aislada sin framework ni base de datos; el de integración prueba varias capas juntas |
