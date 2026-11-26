# ARCHITECTURE

## Vista general

```
Vue 3 (Vite, Pinia, Router) ──HTTP/JSON──► Laravel 13 (API REST, Sanctum)
                                              │            │
                                              ▼            ▼
                                           MySQL 8.4  Node.js (Express + pdfkit)
```

- Vue solo habla con Laravel. Node **solo** lo llama Laravel, con un token compartido entre servicios.
- Node no accede a la BD ni contiene lógica de negocio: recibe JSON de una nómina y devuelve un PDF.

## Estructura del repositorio

```
APP_WEB/
├─ backend/               Laravel (API)
├─ frontend/              Vue 3 + Vite
├─ services/pdf-service/  Microservicio Node (pdfkit)
├─ docker/                Dockerfiles y configuración (nginx, php, etc.)
├─ docs/                  Diagramas, capturas, API docs
├─ CLAUDE.md  ROADMAP.md  PROJECT_CONTEXT.md  ARCHITECTURE.md
├─ DECISIONS.md  LEARNING_PROGRESS.md
└─ docker-compose.yml     (se crea en M0/M8)
```

## Capas del backend (Laravel)

`Route → Controller (fino) → FormRequest (validación) → Service (lógica de negocio) → Model/Eloquent → MySQL`, y `API Resource` para dar forma a la respuesta. `Policy` para autorización en cada recurso.

Implementado en M3: `LeaveRequestService` (reglas de ausencias, transacciones y bloqueos) y `LeaveRequestPolicy`.

Implementado en M4: `Services/Payroll` (`PayrollCalculator` + reglas `PayrollRule`, sin base de datos, y `PayrollService` para generar y cerrar periodos).

Endpoints de ausencias (todos con `auth:sanctum`):
```
GET   /api/leave-requests[?status=]        listado filtrado por rol
POST  /api/leave-requests                  crear (empleado)
PATCH /api/leave-requests/{id}/approve     manager del dpto. o admin
PATCH /api/leave-requests/{id}/reject      ídem, exige comment
PATCH /api/leave-requests/{id}/cancel      solo el propio empleado
GET   /api/leave-balance                   saldo del año en curso
GET   /api/leave-types                     catálogo de tipos (cualquier usuario autenticado)
```

Cada solicitud incluye `can: { review, cancel }`, calculado con `LeaveRequestPolicy` para el usuario actual (D-019).

Endpoints de nóminas (M4, todos con `auth:sanctum`):
```
GET   /api/payroll-periods                  admin
POST  /api/payroll-periods                  admin: {year, month}
POST  /api/payroll-periods/{id}/generate    admin, solo en borrador
PATCH /api/payroll-periods/{id}/close       admin
GET   /api/payslips[?period_id=]            propias cerradas; admin todas
GET   /api/payslips/{id}                    detalle con líneas y snapshot
GET   /api/payslips/{id}/pdf                recibo PDF (mismo permiso que el detalle); 503 si el pdf-service no responde
```

Flujo del PDF (M5, D-023):
```
Vue ─GET /api/payslips/{id}/pdf─► PayslipController@pdf
                                   ├─ Gate 'view' (PayslipPolicy) ── 403 si no
                                   └─ PayslipPdfClient ─POST /payslips/pdf (Bearer token)─► pdf-service (Express)
                                                                                            ├─ validate.js (422)
                                                                                            └─ pdfkit → stream PDF
```
`services/pdf-service/src`: `app.js` (rutas y errores), `auth.js` (token), `validate.js`, `payslipPdf.js`; `server.js` solo arranca. Tests: `npm test` (`node:test`).

Datos demo: `php artisan db:seed --class=PayrollSeeder` (repetible).

## Frontend (Vue 3) — M6

```
frontend/src/
├─ main.js              crea la app: Pinia + Router + assets/main.css
├─ api/http.js          instancia Axios: baseURL /api, Bearer (petición), 401 → login (respuesta)
├─ stores/auth.js       sesión: token (localStorage), user, login/fetchUser/logout
├─ router/index.js      rutas + guard global (meta.public, meta.roles, ?redirect=)
├─ layouts/AppLayout    cabecera con menú por rol, Salir, pie con aviso legal
├─ views/               LoginView, HomeView, EmployeesView, EmployeeFormView, LeaveRequestsView, PayrollPeriodsView, PayslipsView, PayslipDetailView
├─ components/          AppPagination (lee el meta de paginate() de Laravel)
├─ utils/format.js      formatDate
└─ assets/main.css      variables de diseño y estilos globales (sin librería de UI, D-007)
```

En desarrollo, Vite (`:5173`) reenvía `/api` a Laravel (`:8000`), así que hay un solo origen y no hace falta CORS (D-016). El guard y los botones ocultos por rol son comodidad: la autorización real la hacen las Policies.

Patrones previstos: Service layer, Strategy (reglas de complementos/retenciones), Policy. Repository solo si aporta valor.

## Modelo de datos inicial

```
users (id, name, email, password, role)            1─1  employees
positions (id, name)
departments (id, name, manager_id→employees NULL, ON DELETE SET NULL)
employees (id, user_id UNIQUE, department_id, position_id, hire_date, status)
contracts (id, employee_id, type, base_salary, start_date, end_date)
leave_types (id, name, requires_approval, deducts_balance)
leave_balances (id, employee_id, year, days_total, days_used)
leave_requests (id, employee_id, leave_type_id, start_date, end_date, days,
                status[pending|approved|rejected|cancelled],
                reviewed_by→employees, review_comment, reviewed_at)
payroll_periods (id, year, month, status[draft|closed])
payslips (id, employee_id, payroll_period_id, gross, deductions, net, snapshot JSON)
payslip_lines (id, payslip_id, concept, type[earning|deduction], amount)
```

Puntos clave: claves foráneas con restricciones; índices en `(employee_id, status)` y fechas; importes en `DECIMAL` o céntimos (nunca float); `snapshot` para que una nómina cerrada no cambie si cambia el salario; validación de solapes y saldo con transacciones.

> Implementado en M1 (`backend/database/migrations`). Estados y tipos se guardan como texto y se validarán en la aplicación (M2–M4). Cambios futuros: anotar en `DECISIONS.md`.

## Servicios Docker

**Actuales:** `app` (PHP con `artisan serve`, puerto 8000), `mysql` (sin puerto publicado, datos en el volumen `mysql_data`, D-009) y `pdf` (Node 22 alpine, sin puerto publicado, healthcheck en `/health`; `app` espera a que esté sano). Ver D-023.

**Previstos (M8):** `nginx`, `php-fpm`, `mysql`, `pdf`, `frontend` (build estático); en desarrollo, `mailpit` (opcional).

## Seguridad (resumen)

bcrypt, tokens Sanctum, Policies por recurso (evita IDOR), validación en servidor, Eloquent (sin SQLi), CORS restringido, rate limiting en login, secretos solo en `.env`, `.env.example` sin credenciales.
