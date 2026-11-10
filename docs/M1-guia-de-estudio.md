# Guía de estudio del módulo M1: modelo de datos, migraciones y Eloquent

Apuntes de todo lo trabajado en el módulo M1 de *HR Nómina Lite*, escritos para estudiar y para consultar más adelante.

**Fechas de realización:** del 6 al 10 de noviembre de 2026
**Repositorio:** [viballester/hr-nomina-lite](https://github.com/viballester/hr-nomina-lite) · Pull Request #3

## Contenido

1. [Visión general](#1-visión-general)
2. [Diseño del modelo de datos](#2-diseño-del-modelo-de-datos)
3. [Migraciones](#3-migraciones)
4. [Tipos de columna](#4-tipos-de-columna)
5. [Claves foráneas y reglas de borrado](#5-claves-foráneas-y-reglas-de-borrado)
6. [Índices](#6-índices)
7. [Las migraciones del proyecto](#7-las-migraciones-del-proyecto)
8. [Modelos Eloquent](#8-modelos-eloquent)
9. [Factories](#9-factories)
10. [Seeders](#10-seeders)
11. [Cómo se verificó cada paso](#11-cómo-se-verificó-cada-paso)
12. [Problemas encontrados y cómo se resolvieron](#12-problemas-encontrados-y-cómo-se-resolvieron)
13. [Chuleta de comandos](#13-chuleta-de-comandos)
14. [Preguntas de entrevista](#14-preguntas-de-entrevista)
15. [Autoevaluación](#15-autoevaluación)
16. [Glosario](#16-glosario)

---

## 1. Visión general

### Objetivo del módulo

M1 crea la **capa de datos** de la aplicación. Tampoco añade funciones visibles para el usuario, pero deja resueltas tres cosas:

- **Estructura:** las tablas y sus relaciones, con reglas que MySQL hace cumplir por sí mismo.
- **Acceso desde PHP:** un modelo Eloquent por tabla, con sus relaciones.
- **Datos de demo:** una empresa ficticia que se crea con un solo comando.

### Criterio de finalización

M1 se daba por terminado cuando `migrate:fresh --seed` creara desde cero todas las tablas y los datos de demo sin errores, y cuando las relaciones y las reglas de borrado se hubieran probado con datos reales. Se cumplió el 10 de noviembre.

### Resultado

| Elemento | Cantidad |
|---|---|
| Migraciones nuevas | 12 (15 en total, con las de Laravel) |
| Tablas de negocio | 11 |
| Modelos Eloquent | 11 (10 nuevos y `User`) |
| Factories nuevas | 2 (`Employee` y `Contract`) |
| Commits en la rama | 10 |

### Archivos creados o modificados

| Ruta | Contenido |
|---|---|
| `backend/database/migrations/` | Las 12 migraciones nuevas |
| `backend/app/Models/` | Los 10 modelos nuevos y la relación añadida a `User` |
| `backend/database/factories/` | `EmployeeFactory` y `ContractFactory` |
| `backend/database/seeders/DatabaseSeeder.php` | Los datos de demo |
| `docker-compose.yml` | `APP_FAKER_LOCALE: es_ES` |
| `ARCHITECTURE.md`, `DECISIONS.md` | Modelo definitivo y decisiones D-011 y D-012 |

---

## 2. Diseño del modelo de datos

### 2.1 Normalización

**Normalizar es guardar cada dato en un solo sitio.** Si cada empleado guardara el texto «Recursos Humanos», cambiar ese nombre obligaría a modificar muchas filas, y alguna podría quedarse mal.

Por eso `employees` guarda `department_id`, un número que apunta a `departments`, y el nombre vive solo en esa tabla.

**Excepción deliberada:** el `snapshot` de una nómina duplica datos a propósito. Debe conservar cómo eran el salario y el contrato al calcularla, aunque cambien después (D-006).

### 2.2 Cardinalidad

La cardinalidad indica cuántas filas de una tabla se relacionan con cuántas de otra. **Decide en qué tabla va la clave foránea.**

| Tipo | Ejemplo | Dónde va la FK |
|---|---|---|
| **1:N** (uno a muchos) | Un departamento tiene muchos empleados | En el lado «muchos»: `employees.department_id` |
| **1:1** (uno a uno) | Un usuario tiene un empleado | En cualquiera de los dos lados, con un índice único: `employees.user_id` |

**Ejercicio resuelto:** un empleado puede tener varios contratos a lo largo del tiempo. Es una relación 1:N, así que la FK `employee_id` va en `contracts`.

### 2.3 Decisión: tabla `positions` (D-011)

El borrador guardaba el puesto como texto en `employees.job_title`. Se cambió por una tabla `positions`, por dos motivos:

- Evita repetir el nombre del puesto en cada empleado, que es la normalización.
- El MVP incluye gestionar los puestos, y para eso necesitan su propia tabla.

### 2.4 Diagrama del modelo

```text
users ─1:1─ employees ─N:1─ departments ─(manager_id)─> employees
                │  └─N:1─ positions
                │
                ├─1:N─ contracts
                ├─1:N─ leave_balances
                ├─1:N─ leave_requests ─N:1─ leave_types
                │            └─(reviewed_by)─> employees
                └─1:N─ payslips ─N:1─ payroll_periods
                          └─1:N─ payslip_lines
```

### 2.5 Las tablas

| Tabla | Qué guarda | Columnas propias |
|---|---|---|
| `users` | Credenciales y rol | `name`, `email`, `password`, `role` |
| `positions` | Puestos | `name` |
| `departments` | Departamentos | `name`, `manager_id` |
| `employees` | Ficha laboral | `user_id`, `department_id`, `position_id`, `hire_date`, `status` |
| `contracts` | Contratos | `employee_id`, `type`, `base_salary`, `start_date`, `end_date` |
| `leave_types` | Tipos de ausencia | `name`, `requires_approval`, `deducts_balance` |
| `leave_balances` | Días por empleado y año | `employee_id`, `year`, `days_total`, `days_used` |
| `leave_requests` | Solicitudes de ausencia | `employee_id`, `leave_type_id`, `start_date`, `end_date`, `days`, `status`, `reviewed_by`, `review_comment`, `reviewed_at` |
| `payroll_periods` | Meses de nómina | `year`, `month`, `status` |
| `payslips` | Nómina de un empleado en un mes | `employee_id`, `payroll_period_id`, `gross`, `deductions`, `net`, `snapshot` |
| `payslip_lines` | Conceptos de una nómina | `payslip_id`, `concept`, `type`, `amount` |

Todas tienen además `id`, `created_at` y `updated_at`.

### 2.6 Usuario y empleado son tablas distintas

- **`users`** responde a «¿quién puede entrar en la aplicación y con qué permisos?».
- **`employees`** responde a «¿qué datos laborales tiene esta persona?».

Separarlas mantiene cada tabla con una sola responsabilidad. Además, la autenticación de M2 trabaja con `users` sin mezclarse con los datos laborales.

---

## 3. Migraciones

### 3.1 Qué es una migración

Una migración es un **archivo versionado que describe un cambio en la base de datos**. Tiene dos métodos:

- **`up()`:** aplica el cambio.
- **`down()`:** lo deshace.

Como están en Git, cualquiera que clone el proyecto obtiene la misma estructura ejecutando `migrate`.

### 3.2 Estructura de un archivo

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();                       // clave primaria
            $table->string('name')->unique();   // ← aquí van tus columnas
            $table->timestamps();               // created_at y updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
```

Cada línea `$table->...` dentro del bloque es una columna o un índice. **Las columnas propias se escriben entre `id()` y `timestamps()`.**

### 3.3 Crear una tabla o modificar una existente

| Situación | Método | Nombre del comando | `down()` |
|---|---|---|---|
| Tabla nueva | `Schema::create` | `create_xxx_table` | Laravel lo genera: `dropIfExists` |
| Tabla que ya existe | `Schema::table` | `add_xxx_to_yyy_table` | **Hay que escribirlo a mano** |

Al modificar una tabla, `down()` debe deshacer **solo** lo que añadió `up()`:

```php
// up(): añade la columna
$table->string('role')->default('employee')->after('password');

// down(): la elimina, sin tocar el resto de la tabla
$table->dropColumn('role');
```

### 3.4 El orden importa

Laravel ejecuta las migraciones **por orden de fecha**, que forma parte del nombre del archivo (`2026_11_06_104017_...`). Una tabla con FK debe crearse **después** de la tabla a la que apunta.

Por eso las migraciones se generaron en orden: `positions` y `departments` antes que `employees`, y `payroll_periods` antes que `payslips`.

### 3.5 No se edita una migración ya compartida

`users` ya estaba en GitHub. Si se hubiera editado su migración para añadir `role`, quien ya la hubiera ejecutado no vería el cambio, porque Laravel la tiene registrada como aplicada en la tabla `migrations`.

**Regla:** si una migración ya se ha compartido, el cambio va en una migración nueva.

### 3.6 Lotes (*batches*)

`migrate:status` muestra un número de lote junto a cada migración. Las que se ejecutan con un mismo `migrate` forman un lote, y `migrate:rollback --step=N` deshace las últimas N migraciones.

Tras `migrate:fresh`, las 15 aparecían en el lote `[1]`, la prueba de que se habían aplicado de una vez sobre una base vacía.

---

## 4. Tipos de columna

| Método | Tipo en MySQL | Uso en el proyecto |
|---|---|---|
| `id()` | `BIGINT UNSIGNED`, autoincremental | Clave primaria de todas las tablas |
| `foreignId('x_id')` | `BIGINT UNSIGNED` | Columnas FK |
| `string('x')` | `VARCHAR(255)` | Nombres, estados y tipos |
| `text('x')` | `TEXT` | `review_comment` |
| `date('x')` | `DATE` | Fechas sin hora: `hire_date`, `start_date`… |
| `timestamp('x')` | `TIMESTAMP` | Fecha y hora: `reviewed_at` |
| `boolean('x')` | `TINYINT(1)` | `requires_approval`, `deducts_balance` |
| `unsignedTinyInteger('x')` | `TINYINT UNSIGNED` (0–255) | `month` |
| `unsignedSmallInteger('x')` | `SMALLINT UNSIGNED` (0–65.535) | `year`, días |
| `decimal('x', 10, 2)` | `DECIMAL(10,2)` | Todos los importes |
| `json('x')` | `JSON` | `snapshot` |

### 4.1 Modificadores

| Modificador | Qué hace | Ejemplo |
|---|---|---|
| `->nullable()` | Permite que quede vacío (`NULL`) | `end_date` de un contrato indefinido |
| `->default(valor)` | Valor si no se indica ninguno | `status` empieza en `active` |
| `->unique()` | Impide repetir el valor | `name` de un puesto |
| `->after('col')` | Coloca la columna detrás de otra | `role` después de `password` |

### 4.2 Dinero: `DECIMAL`, nunca `float` (D-005)

`float` guarda los números en binario y muchos decimales no se representan con exactitud:

```php
0.1 + 0.2   // 0.30000000000000004
```

En una nómina, estos errores acaban descuadrando céntimos. `DECIMAL(10,2)` guarda el valor exacto: 10 dígitos en total, 2 de ellos decimales. Su máximo es `99999999.99`.

### 4.3 `boolean` en MySQL

MySQL no tiene un tipo booleano propio: lo guarda como `TINYINT(1)`, donde `1` es verdadero y `0` es falso. El cast `boolean` de Eloquent lo convierte en `true` o `false` al leerlo.

### 4.4 Cuándo usar `JSON`

JSON guarda datos estructurados en una sola columna, pero son difíciles de consultar y MySQL no aplica FK dentro de ellos. Solo se usa en `snapshot`, que es una **foto fija** de los datos de cálculo que no debe cambiar ni relacionarse.

### 4.5 Estados guardados como texto

`status`, `type` y `role` se guardan como `string`, sin restringir sus valores en la base de datos. Por ejemplo, MySQL aceptaría un `month` de 13. Esa validación se hará en la aplicación, en M2 a M4.

---

## 5. Claves foráneas y reglas de borrado

### 5.1 Qué es una clave foránea

Una clave foránea (FK) es una columna que apunta al `id` de otra tabla. **MySQL rechaza un valor que no exista**, así que la integridad de los datos no depende solo de que el código PHP esté bien.

### 5.2 Sintaxis

```php
$table->foreignId('department_id')->constrained()->restrictOnDelete();
```

| Parte | Qué hace |
|---|---|
| `foreignId('department_id')` | Crea la **columna**, de tipo `BIGINT UNSIGNED` |
| `constrained()` | Crea la **FK** y deduce la tabla por el nombre: `department_id` → `departments` |
| `restrictOnDelete()` | Define qué pasa al borrar la fila referenciada |

**Cuando el nombre no indica la tabla**, se pasa como argumento:

```php
$table->foreignId('reviewed_by')->nullable()->constrained('employees')->restrictOnDelete();
```

### 5.3 El orden de la cadena importa

Cada método actúa sobre **lo que le devuelve el anterior**:

```text
foreignId(...)  →  devuelve la COLUMNA  →  aquí van nullable(), unique()...
constrained()   →  devuelve la FK       →  aquí van restrictOnDelete()...
```

```php
// ✅ Correcto: unique() actúa sobre la columna
$table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();

// ❌ Incorrecto: unique() actúa sobre la FK y se ignora, SIN ningún error
$table->foreignId('user_id')->constrained()->restrictOnDelete()->unique();
```

### 5.4 Reglas de borrado (D-012)

| Regla | Efecto al borrar la fila referenciada | Método en Laravel |
|---|---|---|
| `RESTRICT` | Impide el borrado | `restrictOnDelete()` |
| `CASCADE` | Borra también las filas hijas | `cascadeOnDelete()` |
| `SET NULL` | Deja la FK vacía (debe ser `nullable`) | `nullOnDelete()` |

**Aplicación en el proyecto:**

| FK | Regla | Motivo |
|---|---|---|
| `payslip_lines.payslip_id` | `CASCADE` | Las líneas no tienen sentido sin su nómina |
| `departments.manager_id` | `SET NULL` | El departamento sigue existiendo sin responsable |
| Todas las demás | `RESTRICT` | Son datos históricos que no deben perderse |

### 5.5 Consecuencia: los empleados no se borran

Como `payslips.employee_id` es `RESTRICT`, no se puede borrar un empleado con nóminas. **Una baja no es un `DELETE`, sino un cambio de `status` a `inactive`.** Así se conserva el historial, que es lo que se espera de una aplicación de RR. HH.

### 5.6 La dependencia circular

`departments.manager_id` apunta a `employees`, y `employees.department_id` apunta a `departments`. Ninguna tabla puede crearse primero con su FK, porque la otra aún no existe.

**Solución en tres pasos:**

1. Crear `departments` con `manager_id` como columna normal y `nullable`, **sin FK**:
   ```php
   $table->unsignedBigInteger('manager_id')->nullable();
   ```
2. Crear `employees`, con su FK hacia `departments`.
3. Añadir la FK de `manager_id` en una migración posterior:
   ```php
   // up()
   $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
   // down()
   $table->dropForeign(['manager_id']);
   ```

Aquí se usa `foreign()` porque la columna ya existe; `foreignId()` crearía una nueva. Y `dropForeign()` elimina la FK pero conserva la columna, que pertenece a otra migración.

---

## 6. Índices

### 6.1 Qué es un índice

Un índice funciona como el índice de un libro: permite a MySQL encontrar filas sin recorrer toda la tabla. Con pocos datos no se nota, pero sí con miles de filas.

### 6.2 `index` y `unique`

| | ¿Acelera las búsquedas? | ¿Impide duplicados? |
|---|---|---|
| `index` | Sí | **No** |
| `unique` | Sí | **Sí** |

Si la regla de negocio dice «no puede repetirse», se usa `unique`. Con `index`, MySQL aceptaría dos nóminas del mismo empleado en el mismo mes, y eso podría acabar en un pago duplicado.

### 6.3 Índices compuestos

Un índice compuesto abarca **varias columnas** y se escribe en una línea propia, con un array:

```php
$table->unique(['employee_id', 'year']);
```

En un `unique` compuesto, lo que no se puede repetir es **la combinación**:

| `employee_id` | `year` | ¿Se permite? |
|---|---|---|
| 1 | 2026 | ✅ |
| 1 | 2027 | ✅ El año es distinto |
| 2 | 2026 | ✅ El empleado es distinto |
| 1 | 2026 | ❌ Esa combinación ya existe |

Un `unique()` en cada columna por separado no serviría: un empleado solo podría tener un saldo en toda su vida.

### 6.4 Índices del proyecto

| Tabla | Índice | Tipo | Para qué |
|---|---|---|---|
| `employees` | `user_id` | Único | Relación 1:1 con `users` |
| `leave_balances` | `employee_id, year` | Único compuesto | Un saldo por empleado y año |
| `leave_requests` | `employee_id, status` | Compuesto | Solicitudes pendientes de un empleado |
| `leave_requests` | `start_date, end_date` | Compuesto | Buscar ausencias entre fechas |
| `payroll_periods` | `year, month` | Único compuesto | Un periodo por mes |
| `payslips` | `employee_id, payroll_period_id` | Único compuesto | Una nómina por empleado y periodo |

### 6.5 MySQL indexa las FK por sí mismo

MySQL crea un índice para cada FK si no existe ya uno que empiece por esa columna. Por eso en `leave_balances` no apareció un índice propio de `employee_id`: reutiliza el compuesto `employee_id, year`, porque `employee_id` es su primera columna.

---

## 7. Las migraciones del proyecto

En orden de ejecución:

| # | Migración | Qué hace |
|---|---|---|
| 1 | `create_positions_table` | Puestos |
| 2 | `create_departments_table` | Departamentos, con `manager_id` sin FK |
| 3 | `create_employees_table` | Empleados, con tres FK |
| 4 | `add_manager_foreign_to_departments_table` | FK de `manager_id`, con `SET NULL` |
| 5 | `add_role_to_users_table` | Columna `role`, con `employee` por defecto |
| 6 | `create_contracts_table` | Contratos, con salario en `DECIMAL` |
| 7 | `create_leave_types_table` | Tipos de ausencia, con dos booleanos |
| 8 | `create_leave_balances_table` | Saldos, con único compuesto |
| 9 | `create_leave_requests_table` | Solicitudes, con dos índices compuestos |
| 10 | `create_payroll_periods_table` | Periodos, con único compuesto |
| 11 | `create_payslips_table` | Nóminas, con `DECIMAL`, `JSON` y único compuesto |
| 12 | `create_payslip_lines_table` | Líneas de nómina, con `CASCADE` |

### Ejemplo completo: `employees`

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
    $table->foreignId('department_id')->constrained()->restrictOnDelete();
    $table->foreignId('position_id')->constrained()->restrictOnDelete();
    $table->date('hire_date');
    $table->string('status')->default('active');
    $table->timestamps();
});
```

### Ejemplo completo: `leave_requests`

```php
Schema::create('leave_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->restrictOnDelete();
    $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
    $table->date('start_date');
    $table->date('end_date');
    $table->unsignedSmallInteger('days');
    $table->string('status')->default('pending');
    $table->foreignId('reviewed_by')->nullable()->constrained('employees')->restrictOnDelete();
    $table->text('review_comment')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->index(['employee_id', 'status']);
    $table->index(['start_date', 'end_date']);
    $table->timestamps();
});
```

`days` se guarda para no recalcularlo. La regla para contarlos, como excluir fines de semana, y las validaciones de solapes y saldo se harán en M3.

---

## 8. Modelos Eloquent

### 8.1 Qué es un modelo

**Un modelo es una clase PHP que representa una tabla, y cada objeto representa una fila.** Eloquent es el ORM de Laravel: traduce entre objetos y SQL.

```php
// En vez de: SELECT * FROM employees WHERE id = 1
$empleado = Employee::find(1);
```

Laravel deduce la tabla del nombre de la clase: `Employee` → `employees`, `LeaveRequest` → `leave_requests`.

### 8.2 Estructura de un modelo

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'type', 'base_salary', 'start_date', 'end_date'])]
class Contract extends Model
{
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
```

**Los dos `use` son cosas distintas:**

| Dónde | Qué hace |
|---|---|
| Arriba, fuera de la clase | **Importa** una clase para poder usar su nombre corto |
| Dentro de la clase | **Añade un trait**: un bloque de métodos reutilizable |

### 8.3 Relaciones

Las FK protegen los datos en MySQL, pero PHP no las conoce. Una relación se la describe al modelo para poder navegar entre tablas:

```php
$empleado->department->name;
```

**Cada FK se declara en los dos modelos, una vez en cada sentido.** La regla para elegir el método:

| Si el modelo... | Usa | Ejemplo |
|---|---|---|
| **Tiene** la FK en su tabla | `belongsTo` | `Employee` tiene `department_id` |
| Es el otro lado, en 1:N | `hasMany` | `Department` tiene muchos empleados |
| Es el otro lado, en 1:1 | `hasOne` | `User` tiene un empleado |

**Convención de nombres:** en singular si devuelve una fila (`department`), en plural si devuelve varias (`employees`).

**Cuando el nombre no coincide con la columna**, se indica la FK:

```php
public function manager(): BelongsTo
{
    return $this->belongsTo(Employee::class, 'manager_id');
}
```

Lo mismo con `reviewer()` en `LeaveRequest`, que usa `reviewed_by`. En cambio, `lines()` en `Payslip` no lo necesita: el nombre del método es libre, y Laravel deduce la FK `payslip_id` a partir del modelo `Payslip`.

### 8.4 Relaciones del proyecto

| Modelo | Relaciones |
|---|---|
| `User` | `employee` (hasOne) |
| `Position` | `employees` (hasMany) |
| `Department` | `employees` (hasMany), `manager` (belongsTo) |
| `Employee` | `user`, `department`, `position` (belongsTo); `contracts`, `leaveBalances`, `leaveRequests`, `payslips` (hasMany) |
| `Contract` | `employee` |
| `LeaveType` | `leaveRequests` (hasMany) |
| `LeaveBalance` | `employee` |
| `LeaveRequest` | `employee`, `leaveType`, `reviewer` |
| `PayrollPeriod` | `payslips` (hasMany) |
| `Payslip` | `employee`, `payrollPeriod` (belongsTo); `lines` (hasMany) |
| `PayslipLine` | `payslip` |

### 8.5 Fillable y la asignación masiva

`#[Fillable([...])]` indica qué columnas se pueden rellenar **en bloque**, con `create([...])` o `update([...])`. Es una protección de seguridad.

**El ataque que evita:** un formulario para solicitar vacaciones envía `start_date` y `end_date`. Un usuario malintencionado añade `status=approved` a la petición. Si `status` fuera rellenable, quedaría aprobada sin que nadie la revisara.

**Campos excluidos a propósito:**

| Modelo | Campos fuera de Fillable | Quién los asignará |
|---|---|---|
| `User` | `role` | El administrador, de forma explícita |
| `LeaveRequest` | `status`, `reviewed_by`, `review_comment`, `reviewed_at` | El código de aprobación de M3 |

**Comprobado:** se intentó crear una solicitud con `status=approved` y Laravel descartó el campo **sin dar ningún error**: quedó en `pending`.

### 8.6 Casts

Un cast convierte el valor automáticamente al leerlo y al guardarlo.

| Cast | Qué devuelve | Ejemplo |
|---|---|---|
| `'date'` | Objeto de fecha (`Carbon`) | `hire_date` |
| `'datetime'` | Objeto de fecha y hora | `reviewed_at` |
| `'decimal:2'` | Texto exacto con 2 decimales, sin pasar por `float` | `2000.5` se lee como `'2000.50'` |
| `'boolean'` | `true` o `false` | `requires_approval` |
| `'array'` | Array de PHP (y lo guarda como JSON) | `snapshot` |

### 8.7 Mayúsculas en los nombres de clase

PHP no distingue mayúsculas en los nombres de clase, pero Laravel **busca el archivo** a partir del nombre, y el contenedor usa Linux, que sí las distingue. `PaySlip::class` buscaría `PaySlip.php`, que no existe: el archivo es `Payslip.php`.

Puede funcionar en Windows y fallar en Docker o en producción. Escribe siempre el nombre exacto del archivo.

---

## 9. Factories

### 9.1 Qué es una factory

Una factory describe **cómo es una fila típica** de una tabla y rellena cada columna con un valor inventado. Sirve para la demo y, en M7, para los tests.

```php
User::factory()->create();                     // 1 usuario inventado
User::factory(10)->create();                   // 10 usuarios
User::factory()->create(['role' => 'admin']);  // inventado, pero con role=admin
```

El array **sustituye** los valores inventados que indiques.

### 9.2 Faker

`fake()` es **Faker**, una librería que inventa nombres, emails y fechas. Se configuró en español añadiendo esta variable a `app` en `docker-compose.yml`:

```yaml
APP_FAKER_LOCALE: es_ES
```

Va en el Compose y no en el `.env` porque no es un secreto ni cambia entre entornos. Para aplicarla hay que recrear el contenedor con `docker compose up -d`.

### 9.3 Requisito: el trait `HasFactory`

Un modelo solo puede usar `::factory()` si incluye `use HasFactory;` dentro de la clase.

### 9.4 Las factories del proyecto

```php
// EmployeeFactory
return [
    'user_id' => User::factory(),
    'hire_date' => fake()->dateTimeBetween('-8 years', '-1 month'),
    'status' => 'active',
];

// ContractFactory
return [
    'employee_id' => Employee::factory(),
    'type' => fake()->randomElement(['indefinido', 'temporal']),
    'base_salary' => fake()->numberBetween(1800, 4500) . '.00',
    'start_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
    'end_date' => null,
];
```

**Detalles de diseño:**

- **Factory dentro de factory:** `'user_id' => User::factory()` crea primero el usuario y usa su id.
- **El salario como texto:** un entero con `'.00'` llega exacto a MySQL. Faker generaría decimales con `float` (D-005).
- **Sin departamento ni puesto:** los indica el seeder, para repartir a los empleados entre los departamentos reales.

### 9.5 Las factories ignoran Fillable

`role` no está en Fillable, pero `User::factory()->create(['role' => 'admin'])` funciona. Las factories son código propio de confianza, no datos que llegan de un formulario.

### 9.6 `null` y `'null'`

```php
'end_date' => null     // ✅ el valor nulo: se guarda NULL
'end_date' => 'null'   // ❌ un texto de 4 letras: no es una fecha válida
```

Lo mismo con `true` y `false`: sin comillas son valores de PHP; con comillas, texto.

---

## 10. Seeders

### 10.1 Qué es un seeder

Un seeder es el **guion que llena la base de datos**: decide qué datos crear y en qué orden, usando las factories.

```bash
docker compose exec app php artisan migrate:fresh --seed
```

**Atención:** `migrate:fresh` **borra todas las tablas** antes de volver a crearlas. Úsalo solo en desarrollo.

### 10.2 Qué crea `DatabaseSeeder`

| Datos | Cantidad | Cómo |
|---|---|---|
| Puestos | 5 | A mano |
| Departamentos | 4 | A mano |
| Tipos de ausencia | 3 | A mano |
| Usuarios demo | 3, uno por rol | Factory con datos fijos |
| Empleados inventados | 20 | Factory, repartidos al azar |
| Contratos y saldos | Uno de cada por empleado | En el método `createEmployee` |
| Nóminas | Ninguna | Las generará el cálculo de M4 |

Los catálogos se escriben a mano porque son pocos, fijos y deben tener nombres realistas.

### 10.3 Usuarios de demo

| Email | Rol | Departamento |
|---|---|---|
| `admin@demo.test` | `admin` | Recursos Humanos |
| `manager@demo.test` | `manager` | Recursos Humanos, como responsable |
| `empleado@demo.test` | `employee` | Recursos Humanos |

La contraseña de todos es `password`. Solo sirve para la demo y nunca debe usarse en producción.

### 10.4 Tipos de ausencia

| Nombre | ¿Requiere aprobación? | ¿Descuenta días? |
|---|---|---|
| Vacaciones | Sí | Sí |
| Asuntos propios | Sí | Sí |
| Baja médica | No | No |

### 10.5 Herramientas de PHP usadas

**Array asociativo:** pares `'clave' => valor`. En un `create()`, la clave es el nombre de la columna:

```php
LeaveType::create([
    'name' => 'Vacaciones',
    'requires_approval' => true,
    'deducts_balance' => true,
]);
```

Cada `create()` crea **una sola fila**.

**`collect()` y `map()`:** `collect()` convierte un array en una colección de Laravel, y `map()` aplica una función a cada elemento:

```php
$positions = collect(['Técnico/a', 'Administrativo/a'])
    ->map(fn ($name) => Position::create(['name' => $name]));
```

`fn ($name) => ...` es una **función flecha**: recibe `$name` y devuelve lo que hay después de `=>`.

**Método privado para no repetir código:** crear un empleado completo exige cuatro pasos y se hace 23 veces:

```php
private function createEmployee(Department $department, Position $position, array $userData = []): Employee
{
    $employee = Employee::factory()->create([
        'user_id' => User::factory()->create($userData)->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    Contract::factory()->create([
        'employee_id' => $employee->id,
        'start_date' => $employee->hire_date,   // el contrato empieza el día del alta
    ]);

    LeaveBalance::create([
        'employee_id' => $employee->id,
        'year' => now()->year,
        'days_total' => 22,
    ]);

    return $employee;
}
```

`private` significa que solo se puede usar desde esta clase. `$userData = []` es un **parámetro opcional**: si no se pasa, vale un array vacío y Faker lo inventa todo.

### 10.6 Resultado verificado

| Comprobación | Resultado |
|---|---|
| Puestos, departamentos, tipos de ausencia | 5, 4 y 3 |
| Usuarios, empleados, contratos, saldos | 23 de cada |
| Roles | 1 admin, 1 manager, 21 employee |
| Contratos con inicio distinto del alta | 0 |
| Contraseña de los usuarios demo | Válida en los tres |

---

## 11. Cómo se verificó cada paso

### 11.1 Revisar antes de ejecutar

Cada migración se revisó **antes** de lanzar `migrate`. Así, los errores se corregían en el archivo sin tener que deshacer nada en la base de datos.

### 11.2 Comprobar lo que existe de verdad

Que `migrate` diga `DONE` no garantiza que la tabla sea correcta: varios errores del módulo **no daban ningún aviso**. Tras cada migración se inspeccionó la tabla real:

```bash
docker compose exec app php artisan db:table employees
```

Muestra las columnas con su tipo, los índices y las FK con su regla de borrado.

### 11.3 Probar que `down()` funciona

```bash
docker compose exec app php artisan migrate:rollback --step=1
docker compose exec app php artisan migrate
```

Si deshacer y rehacer funciona, la migración se puede revertir de verdad.

### 11.4 Probar con datos dentro de una transacción

Una **transacción** agrupa operaciones que se pueden deshacer enteras. Los modelos y las reglas se probaron así:

```php
DB::beginTransaction();
// crear filas, probar relaciones, intentar borrados prohibidos...
DB::rollBack();   // todo vuelve a como estaba
```

Se comprobó que `users` volvía a tener 0 filas tras cada prueba.

### 11.5 Qué se probó con datos

| Regla | Prueba | Resultado |
|---|---|---|
| Índice único de `user_id` | Crear dos empleados para un usuario | Rechazado (error 1062) |
| `RESTRICT` | Borrar un puesto en uso | Rechazado (error 1451) |
| `CASCADE` | Borrar una nómina | Sus líneas se borraron |
| `SET NULL` | Borrar al responsable | `manager_id` pasó a `NULL` |
| Fillable | Crear una solicitud con `status=approved` | Se ignoró: quedó `pending` |
| Casts | Leer importes, booleanos, fechas y JSON | Tipos correctos |

Los dos errores de MySQL que conviene reconocer:

- **1062:** *Duplicate entry*. Se ha violado un índice único.
- **1451:** *Cannot delete or update a parent row*. Una FK con `RESTRICT` lo impide.

### 11.6 Prueba final desde cero

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Las 15 migraciones se aplicaron en un solo lote y el seeder terminó sin errores. Confirma que el orden de los archivos funciona igual que le funcionará a quien clone el repositorio.

---

## 12. Problemas encontrados y cómo se resolvieron

| Síntoma | Causa | Solución | ¿Daba error? |
|---|---|---|---|
| El índice único de `user_id` no se habría creado | `->unique()` encadenado después de `constrained()` | Ponerlo antes: `foreignId('user_id')->unique()->constrained()` | **No** |
| La FK de `contracts` no se habría creado | Errata: `constraint()` en lugar de `constrained()` | Corregir la palabra | **No** |
| Columna `salary` en vez de `base_salary` | No coincidía con el modelo de datos | Usar el nombre acordado | No |
| Faltaban los índices de `leave_requests` | Olvido | Añadir las dos líneas `index` | No |
| Se permitirían periodos y nóminas duplicados | `index` en lugar de `unique` | Usar `unique` | **No** |
| Conceptos de nómina con un espacio en blanco | `->default(' ')` en columnas obligatorias | Quitar el valor por defecto | **No** |
| *Class "App\Models\BelongsTo" not found* | Faltaban los `use` de importación | Importar `Fillable`, `BelongsTo` y `HasMany` | Sí, al usarlo |
| La relación fallaría en Docker | `PaySlip`, `PapySlip`, `Emplyee` | Escribir el nombre exacto de la clase | Sí, al usarlo |
| `service "app" is not running` | Docker Desktop se había cerrado | `docker compose up -d` | Sí |
| `Could not parse 'null'` | `'null'` entre comillas | `null` sin comillas | Sí |
| `Undefined variable $requires_approval` | Sintaxis de array asociativo incorrecta | `'requires_approval' => true`, un `create()` por fila | Sí |
| `migrate:fresh` bloqueado para el asistente | El sistema de permisos protege los comandos destructivos | Ejecutarlo el usuario | — |

**La lección principal:** casi la mitad de los errores **no daban ningún aviso**. La migración se ejecutaba, pero la tabla no tenía lo esperado. Por eso, comprueba siempre el resultado real con `db:table` o con una prueba de datos.

**Fallos silenciosos de Laravel que conviene recordar:**

- Un método desconocido sobre una columna, como `constraint()`, se guarda como anotación y se ignora.
- Un método en el lugar equivocado de la cadena, como `unique()` tras `constrained()`, se ignora.
- Un campo que no está en Fillable se descarta sin aviso.

---

## 13. Chuleta de comandos

### Generar archivos

```bash
docker compose exec app php artisan make:migration create_xxx_table      # Tabla nueva
docker compose exec app php artisan make:migration add_xxx_to_yyy_table  # Modificar tabla
docker compose exec app php artisan make:model Nombre                    # Modelo
docker compose exec app php artisan make:factory NombreFactory --model=Nombre
```

### Migraciones

```bash
docker compose exec app php artisan migrate                   # Aplicar las pendientes
docker compose exec app php artisan migrate:status            # Ver estado y lotes
docker compose exec app php artisan migrate:rollback --step=1 # Deshacer la última
docker compose exec app php artisan migrate:fresh --seed      # BORRA TODO y recrea con datos demo
```

### Inspeccionar la base de datos

```bash
docker compose exec app php artisan db:show                   # Conexión y tablas
docker compose exec app php artisan db:show --counts          # Con número de filas
docker compose exec app php artisan db:table employees        # Columnas, índices y FK
```

### Probar código con Tinker

Tinker es una consola interactiva de PHP con la aplicación cargada:

```bash
docker compose exec app php artisan tinker
```

```php
Employee::count()
Employee::first()->department->name
User::where('email', 'admin@demo.test')->first()->role
exit
```

### Inicio de cada sesión de trabajo

```bash
docker compose up -d
```

Si ya están en marcha, no pasa nada. Docker Desktop debe estar abierto.

---

## 14. Preguntas de entrevista

**¿Cómo resolviste la dependencia circular entre departamentos y empleados?**
Un departamento tiene un responsable, que es un empleado, y cada empleado pertenece a un departamento. Creé `departments` con `manager_id` como columna nullable sin FK, después `employees` con su FK, y en una tercera migración añadí la FK de `manager_id` con `SET NULL`.

**¿Cuándo usas RESTRICT, CASCADE o SET NULL?**
`RESTRICT` para datos históricos, como contratos o nóminas: no deben perderse al borrar algo por error. `CASCADE` cuando los hijos no tienen sentido sin el padre, como las líneas de una nómina. `SET NULL` cuando la relación es opcional, como el responsable de un departamento. Por eso los empleados se dan de baja cambiando su estado, no borrándolos.

**¿Qué diferencia hay entre `index` y `unique`?**
Los dos aceleran las búsquedas, pero solo `unique` impide duplicados. Uso índices únicos compuestos para reglas como «una nómina por empleado y periodo», donde lo que no se repite es la combinación de columnas.

**¿Por qué no usas `float` para el dinero?**
Porque representa los decimales en binario de forma aproximada: `0.1 + 0.2` no da exactamente `0.3`. Uso `DECIMAL(10,2)` en MySQL y el cast `decimal:2` en Eloquent, que devuelve el importe como texto exacto.

**¿Qué es la asignación masiva y cómo la evitas?**
Es rellenar un modelo con todo lo que llega en una petición. Si no se limita, un usuario podría enviar `status=approved` y aprobarse sus propias vacaciones. Con `Fillable` declaro solo los campos permitidos. `role` y los campos de aprobación quedan fuera, y lo comprobé: Laravel descartó el campo.

**¿Por qué no editas una migración que ya está en GitHub?**
Porque quien ya la ejecutó no recibiría el cambio: Laravel la tiene registrada como aplicada. El cambio va en una migración nueva, como hice al añadir `role` a `users`.

**¿Por qué separas `users` y `employees`?**
Responden a cosas distintas: acceso y permisos, frente a datos laborales. Separarlos da a cada tabla una sola responsabilidad y aísla la autenticación de la información de RR. HH.

**¿Qué es el snapshot de una nómina?**
Una copia en JSON de los datos usados al calcularla. Si después cambia el salario, una nómina ya cerrada no debe cambiar. Es una desnormalización deliberada.

**¿Cómo compruebas que tus migraciones son correctas?**
Las reviso antes de ejecutarlas, inspecciono la tabla real con `db:table`, pruebo que `down()` se puede revertir y verifico las reglas con datos dentro de una transacción que luego deshago. Al final, recreo todo desde cero con `migrate:fresh --seed`. Lo hago así porque varios errores, como un `unique()` mal colocado, no daban ningún aviso.

**¿Para qué sirven las factories y los seeders?**
Las factories describen cómo generar filas ficticias; los seeders deciden qué datos crear. Con un solo comando tengo una empresa de demo reproducible, y en M7 las factories servirán para preparar los datos de los tests.

---

## 15. Autoevaluación

Intenta responder sin mirar. La sección indicada contiene la respuesta.

### Diseño

1. ¿Qué es normalizar y qué excepción deliberada tiene el proyecto? ([2.1](#21-normalización))
2. En una relación 1:N, ¿en qué tabla va la FK? ([2.2](#22-cardinalidad))
3. ¿Por qué `users` y `employees` son tablas distintas? ([2.6](#26-usuario-y-empleado-son-tablas-distintas))

### Migraciones

4. ¿Qué diferencia hay entre `Schema::create` y `Schema::table`? ([3.3](#33-crear-una-tabla-o-modificar-una-existente))
5. ¿Por qué importa el orden de las migraciones? ([3.4](#34-el-orden-importa))
6. ¿Por qué no se edita una migración ya compartida? ([3.5](#35-no-se-edita-una-migración-ya-compartida))
7. ¿Qué tipo de columna usas para el dinero y por qué? ([4.2](#42-dinero-decimal-nunca-float-d-005))

### Integridad

8. ¿Por qué `->unique()` debe ir antes de `constrained()`? ([5.3](#53-el-orden-de-la-cadena-importa))
9. ¿Qué regla de borrado usarías en cada FK del proyecto? ([5.4](#54-reglas-de-borrado-d-012))
10. ¿Por qué un empleado no se borra? ([5.5](#55-consecuencia-los-empleados-no-se-borran))
11. ¿Cómo se resuelve la dependencia circular? ([5.6](#56-la-dependencia-circular))
12. ¿Qué diferencia hay entre `index` y `unique`? ([6.2](#62-index-y-unique))

### Eloquent

13. ¿Cuándo se usa `belongsTo` y cuándo `hasMany`? ([8.3](#83-relaciones))
14. ¿Qué ataque evita Fillable? ([8.5](#85-fillable-y-la-asignación-masiva))
15. ¿Por qué `PaySlip::class` puede funcionar en Windows y fallar en Docker? ([8.7](#87-mayúsculas-en-los-nombres-de-clase))
16. ¿Qué diferencia hay entre `null` y `'null'`? ([9.6](#96-null-y-null))

### Ejercicios prácticos

1. Con Tinker, muestra el nombre del departamento y del puesto de `empleado@demo.test`.
2. Con Tinker, cuenta cuántos empleados tiene cada departamento.
3. Sin mirar la guía, escribe una migración `create_holidays_table` para guardar días festivos: `date` única y `name`. Ejecútala, compruébala con `db:table` y reviértela con `migrate:rollback`. Después, borra el archivo.
4. Dentro de una transacción en Tinker, intenta borrar un departamento con empleados. ¿Qué código de error da y por qué?

---

## 16. Glosario

### A–C

- **Array asociativo:** lista de pares `'clave' => valor`.
- **Asignación masiva (*mass assignment*):** rellenar un modelo con un array completo, por ejemplo con `create([...])`.
- **`belongsTo`:** relación desde el modelo que tiene la FK hacia el que es referenciado.
- **`CASCADE`:** regla que borra las filas hijas al borrar la fila referenciada.
- **Cardinalidad:** número de filas de una tabla que se relacionan con las de otra (1:1, 1:N, N:M).
- **Carbon:** librería de fechas que usa Laravel. Los casts `date` y `datetime` devuelven objetos Carbon.
- **Cast:** conversión automática de un valor al leerlo o guardarlo en un modelo.
- **Clave foránea (FK):** columna que apunta al `id` de otra tabla.
- **Clave primaria:** columna que identifica cada fila de forma única; aquí, `id`.
- **`collect()`:** convierte un array en una colección de Laravel.
- **Colección:** objeto de Laravel que envuelve una lista y añade métodos como `map()` o `random()`.
- **`constrained()`:** crea la FK de una columna creada con `foreignId()`.

### D–H

- **`DECIMAL`:** tipo numérico exacto de MySQL, adecuado para importes.
- **Dependencia circular:** dos tablas que se referencian mutuamente.
- **Desnormalización:** duplicar datos a propósito, como en el `snapshot`.
- **`down()`:** método de una migración que deshace su cambio.
- **Eloquent:** ORM de Laravel.
- **Factory:** clase que genera filas con datos ficticios.
- **Faker:** librería que inventa nombres, emails, fechas y otros datos.
- **Fillable:** lista de columnas que admiten asignación masiva.
- **Función flecha:** función corta de PHP: `fn ($x) => ...`.
- **`hasMany`:** relación 1:N desde el lado «uno».
- **`hasOne`:** relación 1:1 desde el lado que no tiene la FK.
- **`HasFactory`:** trait que permite usar `::factory()` en un modelo.

### I–N

- **Import (`use`):** declaración que permite usar una clase por su nombre corto.
- **Índice:** estructura que acelera las búsquedas en una tabla.
- **Índice compuesto:** índice que abarca varias columnas.
- **Índice único:** índice que además impide valores repetidos.
- **Integridad referencial:** garantía de que cada FK apunta a una fila que existe.
- **JSON:** formato de texto para datos estructurados; también un tipo de columna de MySQL.
- **Lote (*batch*):** grupo de migraciones ejecutadas con un mismo `migrate`.
- **Migración:** archivo versionado que describe un cambio en la base de datos.
- **Modelo:** clase PHP que representa una tabla.
- **Normalización:** diseño en el que cada dato se guarda en un solo sitio.
- **`null`:** valor especial que significa «sin valor».
- **`nullable()`:** permite que una columna quede vacía.

### O–S

- **ORM:** capa que traduce entre objetos y tablas.
- **`private`:** visibilidad que limita un método a su propia clase.
- **Relación:** método de un modelo que describe cómo se conecta con otro.
- **`RESTRICT`:** regla que impide borrar una fila referenciada.
- **Rollback:** deshacer migraciones o una transacción.
- **Seeder:** clase que inserta datos iniciales o de demo.
- **`SET NULL`:** regla que deja la FK vacía al borrar la fila referenciada.
- **Snapshot:** copia fija de los datos usados al calcular una nómina.

### T–Z

- **Timestamps:** columnas `created_at` y `updated_at`.
- **Tinker:** consola interactiva de PHP con la aplicación Laravel cargada.
- **Trait:** bloque de métodos reutilizable que se añade a una clase con `use`.
- **Transacción:** grupo de operaciones que se confirman o se deshacen juntas.
- **`unique()`:** crea un índice único.
- **`unsigned`:** número sin signo; no admite negativos.
- **`up()`:** método de una migración que aplica su cambio.
