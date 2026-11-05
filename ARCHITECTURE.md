# ARCHITECTURE

## Vista general

```
Vue 3 (Vite, Pinia, Router) ──HTTP/JSON──► Laravel 11 (API REST, Sanctum)
                                              │            │
                                              ▼            ▼
                                           MySQL 8    Node.js (Express + pdfkit)
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

Patrones previstos: Service layer, Strategy (reglas de complementos/retenciones), Policy. Repository solo si aporta valor.

## Modelo de datos inicial

```
users (id, name, email, password, role)            1─1  employees
departments (id, name, manager_id→employees)
employees (id, user_id, department_id, job_title, hire_date, status)
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

> Borrador: se refinará en M1 y los cambios se anotarán en `DECISIONS.md`.

## Servicios Docker (previstos)

`nginx`, `php-fpm`, `mysql`, `node-pdf`, `frontend` (build estático); en desarrollo, `mailpit` (opcional).

## Seguridad (resumen)

bcrypt, tokens Sanctum, Policies por recurso (evita IDOR), validación en servidor, Eloquent (sin SQLi), CORS restringido, rate limiting en login, secretos solo en `.env`, `.env.example` sin credenciales.
