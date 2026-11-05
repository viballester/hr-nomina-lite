# ROADMAP

**Fecha límite:** 30-nov-2026 · **Cierre objetivo:** 28-nov · **Inicio:** 4-nov-2026
**Dedicación:** ~6 h/día · **Estado global:** M0 completado (verificado desde un clon limpio, 5-nov). Siguiente: M1.

Leyenda: ⬜ pendiente · 🟨 en curso · ✅ completado y verificado

## Módulos (un chat por módulo)

| Módulo | Contenido | Depende de | Estado |
|---|---|---|---|
| M0 | Setup, Git/GitHub, Docker base (PHP + MySQL) | — | ✅ |
| M1 | Modelado ER, migraciones, seeders, factories, Eloquent | M0 | ⬜ |
| M2 | Auth (Sanctum), roles, Policies, CRUD empleados, validación y errores | M1 | ⬜ |
| M3 | Ausencias: solicitud, saldo, aprobación, solapes | M2 | ⬜ |
| M4 | Nóminas: periodos, cálculo simplificado, líneas, snapshot | M2 | ⬜ |
| M5 | Microservicio Node (pdfkit) + integración Laravel↔Node | M4 | ⬜ |
| M6 | Frontend Vue (login, empleados, ausencias, nóminas, dashboard) | M2 (paralelo a M3/M4) | ⬜ |
| M7 | Testing, seguridad (OWASP), rate limiting, CORS | M3–M6 | ⬜ |
| M8 | Docker Compose completo, README, capturas, API docs | todo | ⬜ |

## Calendario

| Semana | Fechas | Módulos | Resultado esperado |
|---|---|---|---|
| 1 | 4–10 nov | M0, M1, inicio M2 | Repo en GitHub, Docker con MySQL, esquema migrado con datos demo, login funcionando |
| 2 | 11–17 nov | fin M2, M3, inicio M6 | CRUD empleados con roles; ausencias solicitud/aprobación vía API con tests; Vue con login y listado |
| 3 | 18–24 nov | M4, M5, M6 | Nómina calculada, recibo PDF descargable; Vue con ausencias, nóminas y dashboard |
| 4 | 25–30 nov | M7, M8 + colchón | Pruebas, seguridad, Docker completo, README con capturas, API documentada. Cierre 28-nov |

**Regla de recorte:** si al final de la semana 2 no están las ausencias con tests, se recorta calendario de equipo y gráficos; nunca pruebas ni Docker.

## MVP (imprescindible)

- Auth con roles `admin`, `manager`, `employee` + Policies
- CRUD empleados, departamentos, puestos, contratos; búsqueda y paginación
- Ausencias con flujo de aprobación y saldo
- Nómina mensual simplificada con snapshot + recibo PDF (Node/pdfkit)
- Dashboard con 4–5 KPIs
- Tests de reglas de negocio, API documentada, Docker Compose, README

## Ampliaciones opcionales (por orden)

1. Calendario de equipo con ausencias
2. Exportación CSV/Excel de nóminas
3. Auditoría de cambios
4. Notificaciones por email (Mailpit)
5. Gráficos en el dashboard
6. CI con GitHub Actions (barato y vistoso: subir prioridad si hay hueco)

## Fuera de alcance

IRPF/Seguridad Social reales, facturación, multiempresa, internacionalización.
