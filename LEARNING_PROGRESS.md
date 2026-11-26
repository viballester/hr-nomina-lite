# LEARNING_PROGRESS

Se actualiza al final de cada módulo. Marcar solo lo **implementado y verificado**.

## Estado por módulo

| Módulo | Conceptos a aprender | Tareas completadas | Pendiente / dudas |
|---|---|---|---|
| M0 Setup y Git | Flujo de ramas, commits convencionales, `.env`, Docker básico | Estructura de carpetas y docs creada (Paso 3). Identidad de Git, `git init`, `.gitattributes` y primer commit verificados. Versiones comprobadas: Git 2.53, PHP 8.3.30, Composer 2.9.5, Node 24.13, npm 11.8. Repo público en GitHub y primer Pull Request. WSL2 y Docker Desktop. Laravel 13 creado en contenedor. Compose con PHP y MySQL 8.4; migraciones verificadas desde un clon limpio de GitHub | Repasar: `DB_HOST=mysql` responde a la red interna de Compose (confundido con `--no-reload`). Primera vez con Docker: seguir explicando los conceptos antes de preguntar |
| M1 BD y modelado | Normalización, FKs, índices, relaciones Eloquent, migraciones, seeders | 15 migraciones (11 tablas de negocio + `users.role`), con `down()` probado. FK con RESTRICT/CASCADE/SET NULL e índices únicos compuestos verificados con datos. 11 modelos con relaciones, casts (`decimal:2`, `boolean`, `array`, `date`) y Fillable (protección de asignación masiva comprobada). Factories de empleado y contrato con Faker `es_ES`. Seeder: 5 puestos, 4 departamentos, 3 tipos de ausencia, 3 usuarios demo por rol y 20 empleados con contrato y saldo | Errores repetidos que vigilar: métodos encadenados en orden incorrecto (`unique()` tras `constrained()`), erratas que fallan en silencio (`constraint()`), `index` vs `unique`, imports `use` olvidados, mayúsculas en nombres de clase (Linux), `'null'` con comillas, sintaxis de arrays asociativos. Sin datos demo de ausencias ni nóminas (M3/M4). Solo Recursos Humanos tiene responsable |
| M2 Auth y API base | MVC, REST, Form Requests, Resources, Sanctum, Policies | Sanctum instalado (`install:api`), login/logout/me con tokens. CRUD de empleados, departamentos y puestos con paginación, búsqueda (`whereHas`, bindings) y baja lógica. `EmployeePolicy`/`DepartmentPolicy`/`PositionPolicy` con `before()` para el admin, filtro de listado por departamento para managers e IDOR cerrado (un `employee` ya no puede ver fichas ajenas). Respuestas JSON coherentes en 401/403/404/422 (`bootstrap/app.php`). 30 tests de integración (Sanctum::actingAs, RefreshDatabase + SQLite, `#[DataProvider]`) | Pendiente repasar: diferencia entre autenticación (401) y autorización (403); por qué `ignore()` hace falta en `update` y no en `store`; por qué una Policy no filtra listas por sí sola. Errores repetidos: `use` olvidados en controladores/Requests (`Gate`, Resources, Requests, modelos), PATCH/PUT confundidos con la ruta sin id, `phpunit.xml` con `<env>` en vez de `<server>` para forzar SQLite cuando Docker fija `DB_CONNECTION` por variable de entorno. CRUD de contratos aplazado a M3/M4 |
| M3 Ausencias | Service layer, transacciones, reglas de negocio, SOLID | `LeaveRequestService` con días laborables (Carbon), solapes, saldo con pendientes reservados (`balanceSummary()`, una sola fuente de verdad) y autoaprobación. Aprobar/rechazar/cancelar con `DB::transaction` + `lockForUpdate` y máquina de estados (`pending → approved/rejected/cancelled`). Errores de negocio como `ValidationException` (422 con el mismo formato que el Form Request). `LeaveRequestPolicy` (`review`: nadie se aprueba a sí mismo, ni el admin; `cancel`). Listado filtrado por rol con `match` cerrado por defecto y `?status=`. Inyección de dependencias en controladores. Seeder que crea ausencias a través del servicio. 58 tests (unitarios con `#[DataProvider]`, integración, `travelTo`) | Errores repetidos: bloquear la solicitud pero olvidar `lockForUpdate` en el saldo (tareas 4 y 5); volver a `create()`+`save()` en vez de `make()`; añadir una línea nueva en vez de modificar la existente (seeder). Pendiente: festivos, medios días, solicitudes que cruzan de año. Concurrencia real solo verificable en MySQL (SQLite ignora los bloqueos) |
| M4 Nóminas | Strategy, precisión monetaria, tests unitarios | **Tarea 1** (rama `feat/m4-nominas`): `PayrollCalculator` con reglas `PayrollRule` (salario base, antigüedad, IRPF y cotización simulados) en céntimos enteros; 7 tests unitarios nuevos, 67 en verde | Tarea 2: `PayrollService` (generar periodo, snapshot, cierre). Tarea 3: API, Policy, Resources y seeder. Tarea 4: cierre y documentación |
| M5 Microservicio Node | Express, pdfkit, integración entre servicios, tokens internos | — | — |
| M6 Frontend Vue | Componentes, Pinia, Router con guards, formularios, consumo de API | **Tareas 1–8 completadas (rama `feat/m6-frontend`); pantallas de nóminas y dashboard pendientes de M4.** Plan de 8 tareas: 1 proyecto, 2 formulario, 3 login real, 4 Pinia, 5 Router + guard + layout, 6 listado empleados, 7 alta/edición empleado, 8 ausencias. Hechas 1–8: Vue 3 + Vite (JS, sin TypeScript), Router, Pinia, Axios. `LoginView` con `ref`, `v-model`, `@submit.prevent`, `v-if`, `:disabled`. Proxy de Vite `/api` → `localhost:8000` (sin CORS en desarrollo). Cliente `src/api/http.js` con `Accept: application/json` e interceptor que añade `Authorization: Bearer`. Store `auth` (token en `localStorage`, `login`, `fetchUser`, `logout`). `catch` con `?.` y `??` (502 del proxy si Laravel está caído). Tarea 5 (escrita por Claude desde el 25-nov, el usuario revisa): router con rutas anidadas (`AppLayout` + hijas), guard global `beforeEach` (meta `public`, `?redirect=`, `fetchUser` tras recargar), catch-all, interceptor de respuesta 401 (borra token y recarga en `/login`), botón Salir (logout revoca el token en el servidor), pie con aviso de datos ficticios, CSS global con variables (`assets/main.css`). Verificado en navegador: 200, 422, 502, token persistente, guard, 401 con token falso, logout 204 + token antiguo 401, móvil sin scroll horizontal. Tarea 6: `EmployeesView` con `v-for` + `:key`, `onMounted`, `watch` con debounce de 300 ms, descarte de respuestas desordenadas (contador de peticiones), paginación con `meta` de Laravel, estado vacío y de error, `meta.roles` en la ruta + comprobación en el guard, enlace del menú solo para admin/manager. Verificado: admin ve 23 (2 páginas), manager solo su departamento, employee redirigido a inicio, 5 teclas = 1 petición, tabla desplazable en móvil. Tarea 7: `EmployeeFormView` único para alta y edición (`props: true`, `defineProps`, `Promise.all`, `novalidate`), errores 422 por campo con `aria-invalid`/`aria-describedby`, baja lógica con `confirm()`, botones Nuevo/Editar solo para admin. Backend: mensajes de validación en español (`lang/es/validation.php`, `lang/es.json`, `APP_LOCALE=es` en Compose), 58 tests en verde. Verificado: 422 vacío y email duplicado, alta, edición, baja (etiqueta roja), manager sin acceso al formulario. Tarea 8: `LeaveRequestsView` (saldo, solicitud con errores de negocio 422 por campo, listado con filtro por estado, aprobar con rechazo por `prompt()` y cancelar con `confirm()`), botones según `can` de la API (D-019), `GET /api/leave-types` nuevo, componente `AppPagination` (props + emit) y `utils/format.js`. 60 tests de backend. Verificado con los 3 roles: solape rechazado, saldo actualizado en cada paso (aprobar, rechazar, cancelar devuelve días), nadie se aprueba a sí mismo, cancelar una ausencia ya empezada muestra el 422 del servicio, móvil sin scroll | Pendiente: responder si `required` en el formulario hace innecesaria la validación del `AuthController`. Revisar el código de las tareas 4 y 5 (guía de revisión en el chat del 25-nov). Nóminas y dashboard dependen de M4. Errores vistos: etiqueta de cierre suelta fuera de `<template>`. Instalar extensión *Vue - Official* en Cursor para el resaltado |
| M7 Testing y seguridad | PHPUnit/Pest, tests de integración, XSS/CSRF/SQLi/IDOR | — | — |
| M8 Despliegue | Docker Compose, entornos, secretos, documentación | — | — |

## Conceptos que debo saber explicar en entrevista

- [ ] Por qué Node es un microservicio y no una segunda API
- [ ] Por qué la lógica va en Services y no en controladores
- [ ] Qué es un snapshot de nómina y por qué
- [ ] Cómo evito IDOR con Policies
- [ ] Por qué no se usa float para dinero
- [ ] Diferencia entre imagen, contenedor, bind mount y volumen con nombre
- [ ] Por qué `DB_HOST` es `mysql` y no `localhost` dentro de Compose
- [ ] Por qué `vendor/` no se sube y `composer.lock` sí
- [ ] Qué problema resolvió `--no-reload` y cómo lo detecté
- [ ] Cómo resolví la dependencia circular entre `departments` y `employees`
- [ ] Cuándo usar RESTRICT, CASCADE o SET NULL (ejemplos del proyecto)
- [ ] Diferencia entre `index` y `unique`, y para qué sirve un índice compuesto
- [ ] Por qué `status` y `role` no están en Fillable (asignación masiva)
- [ ] Por qué no se edita una migración ya compartida
- [ ] Diferencia entre autenticación (401) y autorización (403)
- [ ] Cómo evito IDOR con Policies (ejemplo: empleado 3 pidiendo la ficha 2)
- [ ] Por qué `update` necesita `ignore()` en la regla `unique` y `store` no
- [ ] Por qué una Policy no basta para filtrar un listado (el caso del manager)

- [ ] Por qué las reglas de saldo y solape van en un Service y no en el controlador
- [ ] Qué es una condición de carrera y cómo la evitan `DB::transaction` + `lockForUpdate`
- [ ] Por qué las solicitudes pendientes reservan saldo
- [ ] Por qué "nadie se aprueba a sí mismo" va antes del bypass del admin en la Policy
- [ ] Por qué el filtro de un listado debe ser "cerrado por defecto"
- [ ] Qué es un refactor y cómo demuestran los tests que no cambia el comportamiento

- [ ] Por qué el proxy de Vite evita CORS en desarrollo (y qué cambia en producción)
- [ ] Por qué la validación del servidor es obligatoria aunque el formulario tenga `required`
- [ ] Riesgo de guardar el token en `localStorage` (XSS) y alternativa con cookies `HttpOnly`
- [ ] Por qué ocultar un botón en Vue no es seguridad (la seguridad son las Policies)
- [ ] Por qué la API devuelve `can` en vez de repetir las reglas de la Policy en Vue (D-019)
- [ ] Qué es el debounce y por qué hay que descartar respuestas que llegan desordenadas
- [ ] Props y emits: cómo se comunican un componente padre y uno hijo (`AppPagination`)
- [ ] Por qué un mismo componente sirve para alta y edición (`props: true` en la ruta)

## Notas
- Dedicación estimada: ~6 h/día.
