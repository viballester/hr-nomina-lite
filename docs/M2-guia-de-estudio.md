# Guía de estudio del módulo M2: autenticación, roles y API de empleados

Apuntes de todo lo trabajado en el módulo M2 de *HR Nómina Lite*, escritos para estudiar y para consultar más adelante.

**Fechas de realización:** del 10 al 13 de noviembre de 2026
**Repositorio:** [viballester/hr-nomina-lite](https://github.com/viballester/hr-nomina-lite) · Pull Request #4

## Contenido

1. [Visión general](#1-visión-general)
2. [Sanctum: login, logout y tokens](#2-sanctum-login-logout-y-tokens)
3. [REST, Form Requests y Resources](#3-rest-form-requests-y-resources)
4. [CRUD de empleados](#4-crud-de-empleados)
5. [Búsqueda en el listado](#5-búsqueda-en-el-listado)
6. [Autenticación vs. autorización: Policies](#6-autenticación-vs-autorización-policies)
7. [Formato unificado de errores](#7-formato-unificado-de-errores)
8. [Tests automáticos](#8-tests-automáticos)
9. [CRUD de departamentos y puestos](#9-crud-de-departamentos-y-puestos)
10. [Las rutas del módulo](#10-las-rutas-del-módulo)
11. [Problemas encontrados y cómo se resolvieron](#11-problemas-encontrados-y-cómo-se-resolvieron)
12. [Chuleta de comandos](#12-chuleta-de-comandos)
13. [Preguntas de entrevista](#13-preguntas-de-entrevista)
14. [Glosario](#14-glosario)

---

## 1. Visión general

### Objetivo del módulo

M2 construye la **API REST protegida** sobre la que se apoyará todo lo demás: M3 (ausencias), M4 (nóminas) y M6 (Vue) la consumirán tal cual. El módulo responde a tres preguntas:

- **¿Quién eres?** → autenticación con Sanctum.
- **¿Qué puedes hacer?** → autorización con Policies, según el rol (`admin`, `manager`, `employee`).
- **¿Cómo se comunica el frontend con el backend?** → convenciones REST, Form Requests para validar y Resources para dar formato a las respuestas.

### Criterio de finalización

M2 se daba por terminado cuando el CRUD completo de empleados, departamentos y puestos respondiera con los códigos HTTP correctos para los tres roles, el IDOR del listado de empleados quedara cerrado, y una suite de tests automáticos cubriera todo lo anterior sin tocar la base de datos de desarrollo. Se cumplió el 13 de noviembre, con 30 tests en verde.

### Resultado

| Elemento | Cantidad |
|---|---|
| Controladores nuevos | 4 (`Auth`, `Employee`, `Department`, `Position`) |
| Form Requests | 4 |
| API Resources | 3 |
| Policies | 3 |
| Rutas nuevas | 16 (login, logout, me + 3 `apiResource`) |
| Tests de integración | 30 (66 assertions) |
| Commits en la rama | 14 |

### Archivos creados o modificados

| Ruta | Contenido |
|---|---|
| `backend/app/Http/Controllers/Api/` | `AuthController`, `EmployeeController`, `DepartmentController`, `PositionController` |
| `backend/app/Http/Requests/` | `StoreEmployeeRequest`, `UpdateEmployeeRequest`, `DepartmentRequest`, `PositionRequest` |
| `backend/app/Http/Resources/` | `EmployeeResource`, `DepartmentResource`, `PositionResource` |
| `backend/app/Policies/` | `EmployeePolicy`, `DepartmentPolicy`, `PositionPolicy` |
| `backend/app/Models/User.php` | Trait `HasApiTokens` |
| `backend/routes/api.php` | Todas las rutas de la API |
| `backend/bootstrap/app.php` | Respuestas JSON coherentes para 401/404 |
| `backend/tests/Feature/` | `EmployeeApiTest`, `DepartmentAndPositionApiTest` |
| `backend/phpunit.xml` | Forzar SQLite en los tests |
| `backend/config/sanctum.php`, migración `personal_access_tokens` | Instalación de Sanctum |

---

## 2. Sanctum: login, logout y tokens

### 2.1 Qué resuelve

Una API REST no usa sesiones de navegador. El cliente (Vue, Postman, curl) envía credenciales una vez en `/login` y recibe un **token**. A partir de ahí, cada petición lleva la cabecera:

```
Authorization: Bearer <token>
```

### 2.2 Instalación

```bash
docker compose exec app php artisan install:api
```

Esto generó:

| Archivo | Para qué |
|---|---|
| `routes/api.php` | Rutas con prefijo `/api`, sin sesión ni CSRF |
| `config/sanctum.php` | Configuración de Sanctum |
| Migración `personal_access_tokens` | Guarda el **hash** de cada token, nunca el texto plano |
| `bootstrap/app.php` | Registra `routes/api.php` | 

Y en `app/Models/User.php` se añadió el trait:

```php
use Laravel\Sanctum\HasApiTokens;
// ...
use HasApiTokens, HasFactory, Notifiable;
```

`HasApiTokens` da acceso a `createToken()` y a la relación `tokens()`.

### 2.3 `AuthController`

```php
public function login(Request $request)
{
    $data = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $data['email'])->first();

    if (! $user || ! Hash::check($data['password'], $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Credenciales incorrectas.'],
        ]);
    }

    return response()->json([
        'token' => $user->createToken('api')->plainTextToken,
        'user' => $user,
    ]);
}

public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();
    return response()->noContent();
}

public function me(Request $request)
{
    return response()->json($request->user());
}
```

**Por qué el mensaje de error es siempre el mismo** (`'Credenciales incorrectas.'`), tanto si el email no existe como si la contraseña es incorrecta: decir «ese email no existe» permitiría a un atacante averiguar qué cuentas hay en el sistema (*enumeración de usuarios*).

**Por qué `logout` borra solo `currentAccessToken()`** y no todos los tokens del usuario: borrar todos cerraría la sesión en todos sus dispositivos, que sería una acción distinta.

### 2.4 Las rutas

```php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // el resto de rutas protegidas del módulo van aquí dentro
});
```

`/login` queda **fuera** del grupo protegido: para conseguir el primer token no puede hacer falta ya tener uno.

---

## 3. REST, Form Requests y Resources

### 3.1 Convenciones REST

| Verbo + URL | Acción |
|---|---|
| `GET /employees` | Listar |
| `POST /employees` | Crear |
| `GET /employees/{id}` | Ver uno |
| `PUT`/`PATCH /employees/{id}` | Modificar |
| `DELETE /employees/{id}` | Borrar |

`Route::apiResource('employees', EmployeeController::class)` crea las cinco rutas a la vez, enlazadas a `index`, `store`, `show`, `update` y `destroy`.

### 3.2 Form Request

Una clase dedicada solo a validar, que se ejecuta **antes** de que el controlador reciba la petición. Si los datos no son válidos, responde **422** con los errores sin llegar a ejecutar el método del controlador.

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        // ...
    ];
}
```

Ventaja sobre `$request->validate()` dentro del controlador: separa la responsabilidad de validar de la de coordinar (principio de responsabilidad única, la **S** de SOLID), y permite reutilizar `authorize()` para la autorización (ver sección 6).

**`sometimes` para PATCH parcial:** en `UpdateEmployeeRequest`, cada regla lleva `sometimes` en vez de `required`, para que solo se valide un campo si el cliente lo envía:

```php
'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
```

**`unique` ignorando el propio registro al editar:**

```php
Rule::unique('users', 'email')->ignore($userId)
```

Al editar, el email que llega puede ser el mismo que ya tiene ese usuario. Sin `ignore()`, la regla lo rechazaría por considerarlo «ya existente», cuando en realidad es el suyo. En el alta no hace falta, porque el email todavía no existe en la base de datos.

### 3.3 API Resource

Convierte un modelo en el JSON que se expone al cliente, desacoplando la respuesta de las columnas reales de la tabla:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->user->name,      // aplana la relación
        'email' => $this->user->email,
        'hire_date' => $this->hire_date->toDateString(),
        'status' => $this->status,
        'department' => ['id' => $this->department->id, 'name' => $this->department->name],
        'position' => ['id' => $this->position->id, 'name' => $this->position->name],
    ];
}
```

Ventajas: se decide campo a campo qué sale (nunca se filtra `password`), se pueden aplanar relaciones, y si cambia una columna en la BD el contrato con el frontend no se rompe.

**Problema N+1:** listar 15 empleados y acceder a `$employee->user` en cada uno lanza 1 + 15 consultas. La solución es **eager loading**:

```php
Employee::with(['user', 'department', 'position'])->paginate(15);
```
Así son solo 4 consultas, sea cual sea el número de empleados.

---

## 4. CRUD de empleados

### 4.1 Por qué el alta crea dos filas

`Employee` no tiene nombre ni email: esos datos viven en su `User` relacionado por `user_id`. Dar de alta a un empleado significa crear un `User` y un `Employee` a la vez. Para que nunca quede un usuario sin su ficha (o al revés) si algo falla a mitad de camino, se usa una **transacción**:

```php
$employee = DB::transaction(function () use ($data) {
    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make(Str::password(16)),
    ]);

    return Employee::create([
        'user_id' => $user->id,
        'department_id' => $data['department_id'],
        'position_id' => $data['position_id'],
        'hire_date' => $data['hire_date'],
    ]);
});

$employee = $employee->fresh(['user', 'department', 'position']);

return (new EmployeeResource($employee))->response()->setStatusCode(201);
```

`DB::transaction` aplica la regla de **todo o nada**: si cualquier cosa falla dentro, MySQL deshace toda la operación (*rollback*). Se verificó provocando un fallo a propósito (`throw new \Exception(...)`) y comprobando que el usuario tampoco se había creado.

**Por qué `fresh()` y no `load()`:** `Employee::create()` devuelve el objeto con los valores que se le pasaron. `status` no se pasa (toma su valor por defecto `active` en la base de datos), así que el objeto en memoria no lo tiene. `load()` solo añade relaciones; `fresh()` vuelve a leer **toda la fila** desde la BD, con el valor real de `status`.

**Por qué el alta genera una contraseña aleatoria** (`Str::password(16)`): el admin crea la ficha, pero nadie conoce esa contraseña. El flujo para que el empleado la establezca queda fuera del MVP.

### 4.2 Edición parcial

```php
public function update(UpdateEmployeeRequest $request, Employee $employee)
{
    $data = $request->validated();

    DB::transaction(function () use ($data, $employee) {
        $employee->user->update(Arr::only($data, ['name', 'email']));
        $employee->update(Arr::only($data, ['department_id', 'position_id', 'hire_date', 'status']));
    });

    return new EmployeeResource($employee->fresh(['user', 'department', 'position']));
}
```

`Arr::only($data, [...])` reparte los campos validados entre `User` y `Employee`, cada uno con solo sus columnas. Si no llega ningún campo para un modelo, `update([])` no hace nada: no hace falta un `if`.

### 4.3 Baja lógica, no borrado físico

```php
public function destroy(Employee $employee)
{
    Gate::authorize('delete', $employee);

    $employee->update(['status' => 'inactive']);

    return response()->noContent();
}
```

Un empleado nunca se borra: sus contratos y nóminas deben conservarse. Además, las claves foráneas `RESTRICT` de M1 lo impedirían. Se prefirió una columna `status` ya existente antes que añadir `SoftDeletes` (que oculta filas por defecto), porque un empleado dado de baja **debe seguir apareciendo** en listados e informes de RR. HH.

### 4.4 `route model binding`

```php
public function show(Employee $employee)
```

Laravel busca automáticamente el `Employee` cuyo id coincide con el `{employee}` de la URL. Si no existe, lanza un 404 **antes** de entrar en el método: no hay que escribir `Employee::findOrFail($id)` a mano.

---

## 5. Búsqueda en el listado

```php
public function index(Request $request)
{
    $search = $request->query('search');

    $employees = Employee::with(['user', 'department', 'position'])
        ->when($search, function ($query, $search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->orderBy('id')
        ->paginate(15)
        ->withQueryString();

    return EmployeeResource::collection($employees);
}
```

- **`when($search, ...)`**: la condición solo se añade a la consulta si `$search` tiene valor. Evita un `if` envolviendo toda la query.
- **`whereHas('user', ...)`**: filtra empleados según una condición sobre su relación (`name`/`email` viven en `users`, no en `employees`). Genera un `WHERE EXISTS (...)`.
- **El `orWhere` va dentro del `whereHas`**, para que quede agrupado como `(name LIKE ... OR email LIKE ...)`. Si estuviera fuera, el `OR` se mezclaría con el resto de condiciones de la consulta.
- **`withQueryString()`**: conserva `?search=...` en los enlaces `next`/`prev` de la paginación.
- **Inyección SQL**: Eloquent envía el valor de búsqueda como *binding*, separado de la consulta SQL. Se probó enviando `' OR 1=1 -- ` como búsqueda: devolvió 0 resultados en lugar de toda la tabla, confirmando que no se ejecuta como código.
- **Mayúsculas y acentos**: la *collation* por defecto de MySQL (`utf8mb4_0900_ai_ci`) ignora mayúsculas y acentos, así que buscar `avila` encuentra a «Ávila» sin tratamiento especial.

**Límite conocido:** `whereHas` + `LIKE '%...%'` no usa índices. Con los pocos empleados del MVP no afecta; con decenas de miles, haría falta un índice `FULLTEXT`.

---

## 6. Autenticación vs. autorización: Policies

| | Pregunta | Mecanismo | Si falla |
|---|---|---|---|
| Autenticación | ¿Quién eres? | `auth:sanctum` | 401 |
| Autorización | ¿Puedes hacer esto con este recurso? | Policy | 403 |

### 6.1 Reglas por rol

| Acción | admin | manager | employee |
|---|---|---|---|
| Listar empleados | todos | solo su departamento | ❌ 403 |
| Ver ficha de empleado | todas | su departamento | solo la suya |
| Alta / editar / baja de empleado | ✅ | ❌ | ❌ |
| Listar / ver departamentos y puestos | ✅ | ✅ | ✅ |
| Crear / editar / borrar departamentos y puestos | ✅ | ❌ | ❌ |

### 6.2 `EmployeePolicy`

```php
public function before(User $user): ?bool
{
    return $user->role === 'admin' ? true : null;
}

public function viewAny(User $user): bool
{
    return $user->role === 'manager';
}

public function view(User $user, Employee $employee): bool
{
    if ($user->role === 'manager') {
        return $employee->department_id === $user->employee?->department_id;
    }

    return $employee->user_id === $user->id;
}
```

- **`before()`** se ejecuta antes que cualquier otro método: si devuelve `true`, autoriza directamente; si devuelve `null`, sigue con el método normal. Evita repetir `role === 'admin'` en cada método.
- **`?->`** (operador *nullsafe*): si el usuario no tiene ficha de empleado, la expresión da `null` en vez de romper, y la comparación resulta `false`.

### 6.3 Dónde se pregunta a la Policy

- **En el controlador**, para acciones sin Form Request: `Gate::authorize('view', $employee)`.
- **En el Form Request**, para alta y edición: `$this->user()->can('create', Employee::class)`. Así se comprueba el permiso **antes** de validar los datos; si no, un usuario sin permiso que enviara datos inválidos recibiría un 422 (revelando qué campos espera la API) en lugar de un 403 directo.

### 6.4 Una Policy no filtra listas

Una Policy responde sí/no sobre **un** elemento. Para que el listado de un manager muestre solo su departamento hace falta además **filtrar la consulta**:

```php
->when($user->role === 'manager', fn ($q) => $q->where('department_id', $user->employee?->department_id))
```

La Policy decide *quién puede listar* (`viewAny`); el controlador decide *qué ve cada uno* dentro de esa consulta.

### 6.5 El IDOR que se cerró

Antes de la Policy, cualquier usuario autenticado podía pedir `/api/employees/{id}` cambiando el número y ver la ficha de cualquier persona. Es un **IDOR** (*Insecure Direct Object Reference*), del OWASP Top 10 (*Broken Access Control*). Con `view()` comprobando `department_id` o `user_id`, un `employee` que pide la ficha de un compañero recibe **403**.

### 6.6 `DepartmentPolicy` y `PositionPolicy`

Mismo patrón que `EmployeePolicy`, pero más simples: `viewAny` y `view` devuelven `true` para cualquier usuario autenticado (los necesitan los desplegables del formulario de empleado), y `create`/`update`/`delete` devuelven `false` porque solo el admin puede, cubierto por `before()`.

---

## 7. Formato unificado de errores

`bootstrap/app.php` ya traía de la instalación de Sanctum:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

Esto hace que cualquier error en rutas `api/*` responda en JSON, incluso sin la cabecera `Accept`. Se añadieron dos correcciones:

**1. Redirección de invitados → 401 en JSON, no 500.** El middleware de autenticación intentaba redirigir a una ruta `login` nombrada que no existe en esta API (la nuestra es `POST /api/login`, sin nombre), y eso reventaba con un 500:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
})
```

**2. 404 limpio, sin exponer nombres internos de clases:**

```php
$exceptions->render(function (NotFoundHttpException $e, Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Recurso no encontrado.'], 404);
    }
});
```

### Formato resultante

| Código | Cuerpo |
|---|---|
| 401 | `{"message": "Unauthenticated."}` |
| 403 | `{"message": "This action is unauthorized."}` |
| 404 | `{"message": "Recurso no encontrado."}` |
| 409 | `{"message": "No se puede borrar: ... tiene empleados."}` |
| 422 | `{"message": "...", "errors": {"campo": ["..."]}}` |

**Nota sobre `APP_DEBUG`:** con `APP_DEBUG=true` (desarrollo), los 500 y los 403 siguen mostrando la traza completa debajo del `message`, útil para depurar. En producción (M8) se pondrá `APP_DEBUG=false` y esos casos solo dirán `"Server Error"`, sin revelar archivos ni código.

---

## 8. Tests automáticos

### 8.1 Por qué

Todo lo probado a mano con `curl` en las tareas anteriores (login, matriz de roles, IDOR, validación, transacción) se convirtió en tests que corren en segundos y detectan una regresión al instante.

### 8.2 Configuración: SQLite en memoria

```xml
<server name="DB_CONNECTION" value="sqlite" force="true"/>
<server name="DB_DATABASE" value=":memory:" force="true"/>
```

Los tests corren contra una base de datos en RAM, nunca contra el MySQL de desarrollo. **Importante:** se usa `<server>` y no `<env>`, porque `docker-compose.yml` fija `DB_CONNECTION: mysql` como variable de entorno del contenedor, y esa variable gana a un `<env>` de `phpunit.xml`. `<server>` sobrescribe `$_SERVER`, que Laravel lee antes.

### 8.3 Patrón de los tests

```php
use RefreshDatabase; // migra y envuelve cada test en una transacción que se deshace al final

Sanctum::actingAs($user); // simula login sin pasar por /login

$this->getJson('/api/employees')
    ->assertOk()
    ->assertJsonPath('meta.total', 2);

$this->assertDatabaseHas('employees', ['id' => $id, 'status' => 'inactive']);
```

- **Arrange / Act / Assert**: preparar datos → hacer la petición → comprobar respuesta y/o base de datos.
- **`assertDatabaseMissing`** es tan importante como comprobar el código HTTP: en el alta de un manager sin permiso, no basta con el 403; hay que comprobar que **no se escribió nada**.

### 8.4 `#[DataProvider]` (PHPUnit 12)

En PHPUnit 12 el comentario `@dataProvider` ya no funciona; se usa el atributo:

```php
public static function recursos(): array
{
    return [
        'departments' => ['departments', Department::class, 'department_id'],
        'positions' => ['positions', Position::class, 'position_id'],
    ];
}

#[DataProvider('recursos')]
public function test_admin_crea_un_registro(string $uri, string $model, string $foreignKey): void { /* ... */ }
```

Permite ejecutar el mismo test con `departments` y con `positions` sin duplicar el código; PHPUnit los cuenta como tests independientes.

### 8.5 Resultado final

**30 tests, 66 assertions, 0 failures.**

| Archivo | Qué cubre |
|---|---|
| `EmployeeApiTest.php` | Login, 401 sin token, matriz de roles (listar/ver), IDOR, alta con transacción, validación, baja lógica |
| `DepartmentAndPositionApiTest.php` | Listado para cualquier rol, creación restringida al admin, nombre duplicado, edición, borrado con/sin empleados (409/204), parametrizado para los dos recursos |

---

## 9. CRUD de departamentos y puestos

Mismo patrón que empleados, en versión simple: solo `name` (y `manager_id` en `Department`, no expuesto todavía).

```php
public function destroy(Department $department)
{
    Gate::authorize('delete', $department);

    if ($department->employees()->exists()) {
        return response()->json(['message' => 'No se puede borrar: el departamento tiene empleados.'], 409);
    }

    $department->delete();
    return response()->noContent();
}
```

- **Aquí sí hay borrado físico** (no baja lógica), porque no hay obligación legal de conservar un departamento o un puesto.
- **`employees()->exists()`** lanza un `SELECT EXISTS(...)`, mucho más barato que contar o cargar filas, solo para saber si hay alguna.
- **409 Conflict** y no 422: los datos de la petición son correctos; lo que falla es que choca con el estado actual de los datos (hay empleados que dependen de ese registro). Sin esta comprobación, la clave foránea `RESTRICT` de M1 lanzaría un error de MySQL que llegaría como un 500 feo.

---

## 10. Las rutas del módulo

```php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);
});
```

---

## 11. Problemas encontrados y cómo se resolvieron

| Problema | Causa | Solución |
|---|---|---|
| `Class "...\Api\EmployeeResource" not found` (y variantes con `StoreEmployeeRequest`, `Gate`, `PositionRequest`, `Rule`, `Position`...) | `use` olvidado: PHP busca la clase en el namespace del archivo actual si no se importa | Añadir el `use` correspondiente arriba del archivo. Error repetido muchas veces en el módulo; revisar siempre el namespace que aparece en el mensaje |
| Código del alta apareció dentro de `update()` | Al copiar/pegar entre métodos se mezclaron los cuerpos | Revisar qué método ejecuta cada verbo HTTP con `route:list --path=api` |
| `Method ...Request::validated does not exist` | El parámetro del método seguía tipado como `Request` genérico en vez del Form Request concreto | Cambiar el tipo del parámetro: solo un Form Request tiene `validated()` |
| `"status": null` en la respuesta del alta | `Employee::create()` devuelve el objeto con los valores pasados, no los que pone la BD por defecto | Usar `fresh()` en vez de `load()` tras crear, para releer la fila completa |
| 500 en vez de 401 al pedir una ruta protegida sin token (solo sin la cabecera `Accept`) | El middleware de auth intentaba redirigir a una ruta `login` nombrada inexistente en la API | `redirectGuestsTo` devolviendo `null` para rutas `api/*` |
| Comandos `curl.exe` con `-d '{\"...\"}'` fallaban en PowerShell 5.1 | PowerShell elimina las comillas dobles al pasar argumentos a programas externos | Escribir el JSON en un archivo y usar `--data-binary "@body.json"`, o hacer las pruebas desde Bash |
| Los tests vaciaban la BD de desarrollo (MySQL, 0 usuarios tras ejecutar `php artisan test`) | `docker-compose.yml` fija `DB_CONNECTION: mysql` como variable de entorno del contenedor, que gana a un `<env>` de `phpunit.xml` | Cambiar a `<server ... force="true">` en `phpunit.xml`, que sobrescribe `$_SERVER` antes de que Laravel lo lea |
| `@dataProvider` (comentario) no ejecutaba los tests parametrizados (`ArgumentCountError`) | PHPUnit 12 retiró el soporte de providers por comentario | Usar el atributo `#[DataProvider('metodo')]` |
| `DepartmentResource` usado por error en `PositionController::update()` | Copiar/pegar entre controladores gemelos sin terminar de adaptar el nombre | Revisar cada clase copiada contra su original, campo a campo |

---

## 12. Chuleta de comandos

```bash
# Instalar Sanctum
docker compose exec app php artisan install:api

# Generar un controlador de API con los 5 métodos REST
docker compose exec app php artisan make:controller Api/EmployeeController --api --model=Employee

# Generar Resource, Form Request y Policy
docker compose exec app php artisan make:resource EmployeeResource
docker compose exec app php artisan make:request StoreEmployeeRequest
docker compose exec app php artisan make:policy EmployeePolicy --model=Employee

# Ver las rutas de la API
docker compose exec app php artisan route:list --path=api

# Limpiar caché de rutas tras un cambio que no se refleja
docker compose exec app php artisan route:clear

# Ejecutar los tests
docker compose exec app php artisan test

# Reconstruir la BD de desarrollo con datos demo
docker compose exec app php artisan migrate:fresh --seed
```

---

## 13. Preguntas de entrevista

- [ ] Diferencia entre autenticación (401) y autorización (403).
- [ ] Cómo evito IDOR con Policies (ejemplo: un `employee` pidiendo la ficha de un compañero por id).
- [ ] Por qué `update` necesita `ignore()` en la regla `unique` y `store` no.
- [ ] Por qué una Policy no basta para filtrar un listado; qué hace falta además para el manager.
- [ ] Qué es un Form Request y por qué la validación no va en el controlador.
- [ ] Por qué la baja de un empleado es lógica (`status`) y no un `DELETE` físico, y por qué departamentos y puestos sí se borran físicamente.
- [ ] Qué resuelve una transacción (`DB::transaction`) y qué pasaría sin ella en el alta de un empleado.
- [ ] Qué es el problema N+1 y cómo se evita con `with()`.
- [ ] Cómo evita Eloquent la inyección SQL en una búsqueda con `LIKE`.
- [ ] Por qué `/login` queda fuera del grupo de rutas protegidas por `auth:sanctum`.

---

## 14. Glosario

| Término | Significado |
|---|---|
| **Token de acceso personal** | Cadena que identifica a un usuario autenticado sin usar sesiones; Sanctum guarda su hash, no el texto plano |
| **Form Request** | Clase dedicada a validar una petición antes de que llegue al controlador |
| **API Resource** | Clase que da formato a la respuesta JSON de un modelo, desacoplada de sus columnas reales |
| **Policy** | Clase que decide si un usuario puede hacer una acción sobre un modelo concreto |
| **`before()` de una Policy** | Método que se evalúa antes que cualquier otro; si devuelve `true`/`false`, decide directamente; si devuelve `null`, sigue con el método normal |
| **IDOR** | *Insecure Direct Object Reference*: acceder a un recurso ajeno solo cambiando su identificador en la URL |
| **Route model binding** | Laravel resuelve automáticamente un parámetro de ruta al modelo correspondiente, o lanza 404 si no existe |
| **Transacción** | Bloque de operaciones de base de datos que se aplican todas o ninguna |
| **Problema N+1** | Una consulta por cada fila de una lista para cargar su relación, en vez de una sola consulta con `with()` |
| **`whereHas`** | Filtra un modelo según una condición sobre una relación suya |
| **Baja lógica** | Marcar un registro como inactivo (`status`) en vez de borrarlo físicamente, para conservar su historial |
| **`#[DataProvider]`** | Atributo de PHPUnit 12 que ejecuta un mismo test con varios conjuntos de datos |
