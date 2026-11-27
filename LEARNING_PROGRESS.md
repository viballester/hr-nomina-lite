# LEARNING_PROGRESS

Se actualiza al final de cada módulo. Marcar solo lo **implementado y verificado**.

## Estado por módulo

| Módulo | Conceptos a aprender | Tareas completadas | Pendiente / dudas |
|---|---|---|---|
| M0 Setup y Git | Flujo de ramas, commits convencionales, `.env`, Docker básico | Estructura de carpetas y docs creada (Paso 3). Identidad de Git, `git init`, `.gitattributes` y primer commit verificados. Versiones comprobadas: Git 2.53, PHP 8.3.30, Composer 2.9.5, Node 24.13, npm 11.8. Repo público en GitHub y primer Pull Request. WSL2 y Docker Desktop. Laravel 13 creado en contenedor. Compose con PHP y MySQL 8.4; migraciones verificadas desde un clon limpio de GitHub | Repasar: `DB_HOST=mysql` responde a la red interna de Compose (confundido con `--no-reload`). Primera vez con Docker: seguir explicando los conceptos antes de preguntar |
| M1 BD y modelado | Normalización, FKs, índices, relaciones Eloquent, migraciones, seeders | 15 migraciones (11 tablas de negocio + `users.role`), con `down()` probado. FK con RESTRICT/CASCADE/SET NULL e índices únicos compuestos verificados con datos. 11 modelos con relaciones, casts (`decimal:2`, `boolean`, `array`, `date`) y Fillable (protección de asignación masiva comprobada). Factories de empleado y contrato con Faker `es_ES`. Seeder: 5 puestos, 4 departamentos, 3 tipos de ausencia, 3 usuarios demo por rol y 20 empleados con contrato y saldo | Errores repetidos que vigilar: métodos encadenados en orden incorrecto (`unique()` tras `constrained()`), erratas que fallan en silencio (`constraint()`), `index` vs `unique`, imports `use` olvidados, mayúsculas en nombres de clase (Linux), `'null'` con comillas, sintaxis de arrays asociativos. Sin datos demo de ausencias ni nóminas (M3/M4). Solo Recursos Humanos tiene responsable |
| M2 Auth y API base | MVC, REST, Form Requests, Resources, Sanctum, Policies | Sanctum instalado (`install:api`), login/logout/me con tokens. CRUD de empleados, departamentos y puestos con paginación, búsqueda (`whereHas`, bindings) y baja lógica. `EmployeePolicy`/`DepartmentPolicy`/`PositionPolicy` con `before()` para el admin, filtro de listado por departamento para managers e IDOR cerrado (un `employee` ya no puede ver fichas ajenas). Respuestas JSON coherentes en 401/403/404/422 (`bootstrap/app.php`). 30 tests de integración (Sanctum::actingAs, RefreshDatabase + SQLite, `#[DataProvider]`) | Pendiente repasar: diferencia entre autenticación (401) y autorización (403); por qué `ignore()` hace falta en `update` y no en `store`; por qué una Policy no filtra listas por sí sola. Errores repetidos: `use` olvidados en controladores/Requests (`Gate`, Resources, Requests, modelos), PATCH/PUT confundidos con la ruta sin id, `phpunit.xml` con `<env>` en vez de `<server>` para forzar SQLite cuando Docker fija `DB_CONNECTION` por variable de entorno. CRUD de contratos aplazado a M3/M4 |
| M3 Ausencias | Service layer, transacciones, reglas de negocio, SOLID | `LeaveRequestService` con días laborables (Carbon), solapes, saldo con pendientes reservados (`balanceSummary()`, una sola fuente de verdad) y autoaprobación. Aprobar/rechazar/cancelar con `DB::transaction` + `lockForUpdate` y máquina de estados (`pending → approved/rejected/cancelled`). Errores de negocio como `ValidationException` (422 con el mismo formato que el Form Request). `LeaveRequestPolicy` (`review`: nadie se aprueba a sí mismo, ni el admin; `cancel`). Listado filtrado por rol con `match` cerrado por defecto y `?status=`. Inyección de dependencias en controladores. Seeder que crea ausencias a través del servicio. 58 tests (unitarios con `#[DataProvider]`, integración, `travelTo`) | Errores repetidos: bloquear la solicitud pero olvidar `lockForUpdate` en el saldo (tareas 4 y 5); volver a `create()`+`save()` en vez de `make()`; añadir una línea nueva en vez de modificar la existente (seeder). Pendiente: festivos, medios días, solicitudes que cruzan de año. Concurrencia real solo verificable en MySQL (SQLite ignora los bloqueos) |
| M4 Nóminas | Strategy, precisión monetaria, tests unitarios | **Tarea 1** (rama `feat/m4-nominas`): `PayrollCalculator` con reglas `PayrollRule` (salario base, antigüedad, IRPF y cotización simulados) en céntimos enteros; 7 tests unitarios. **Tarea 2:** `PayrollService` (generar, regenerar y cerrar con transacción y bloqueo del periodo, snapshot); 6 tests de integración, 73 en verde, probado en MySQL. **Tarea 3:** API de periodos y nóminas, `PayrollPeriodPolicy` y `PayslipPolicy`, Resources y `PayrollSeeder`; 81 tests en verde, probada con curl. **Tarea 4:** seed completo probado desde cero (SQLite en memoria) y documentación | Responder las preguntas de repaso de M4. Pendiente: prorrateo, pagas extra, bajas a mitad de mes; concurrencia no probada en MySQL; `APP_DEBUG=false` en producción (M7/M8) |
| M5 Microservicio Node | Express, pdfkit, integración entre servicios, tokens internos | Rama `feat/m5-pdf`. **T1:** proyecto Express 5 (ES modules), `createApp` separado de `server.js`, `GET /health`, Dockerfile alpine con usuario `node`. **T2:** middleware de token Bearer con `timingSafeEqual` y fallo cerrado sin token. **T3:** `POST /payslips/pdf` con validación manual (422), manejador de errores de Express (400 sin stack) y recibo pdfkit en streaming, con aviso de demo; `formatEuros` sin float. **T4:** servicio `pdf` en Compose (sin puerto, healthcheck, `depends_on: service_healthy`), `PDF_SERVICE_TOKEN` en `.env`; importes acotados por un aviso de ReDoS. **T5:** `PayslipPdfClient`, `PdfServiceUnavailableException` (503), `GET /api/payslips/{id}/pdf` con la Policy `view`; probado con curl y los 3 roles (200/403/401/503). **T6:** 5 tests con `Http::fake()` (contrato, `assertNothingSent` sin permiso, 503). 11 tests Node + 86 Laravel | Revisar el código de M5 y responder las preguntas de repaso. Error visto: `sed` que borra las `\` de una regex (lo detectaron los tests). Pendiente para M7/M8: `APP_DEBUG=true` muestra trazas en 403 |
| M6 Frontend Vue | Componentes, Pinia, Router con guards, formularios, consumo de API | **Tareas 1–10 completadas: M6 terminado.** Plan de 8 tareas: 1 proyecto, 2 formulario, 3 login real, 4 Pinia, 5 Router + guard + layout, 6 listado empleados, 7 alta/edición empleado, 8 ausencias. Hechas 1–8: Vue 3 + Vite (JS, sin TypeScript), Router, Pinia, Axios. `LoginView` con `ref`, `v-model`, `@submit.prevent`, `v-if`, `:disabled`. Proxy de Vite `/api` → `localhost:8000` (sin CORS en desarrollo). Cliente `src/api/http.js` con `Accept: application/json` e interceptor que añade `Authorization: Bearer`. Store `auth` (token en `localStorage`, `login`, `fetchUser`, `logout`). `catch` con `?.` y `??` (502 del proxy si Laravel está caído). Tarea 5 (escrita por Claude desde el 25-nov, el usuario revisa): router con rutas anidadas (`AppLayout` + hijas), guard global `beforeEach` (meta `public`, `?redirect=`, `fetchUser` tras recargar), catch-all, interceptor de respuesta 401 (borra token y recarga en `/login`), botón Salir (logout revoca el token en el servidor), pie con aviso de datos ficticios, CSS global con variables (`assets/main.css`). Verificado en navegador: 200, 422, 502, token persistente, guard, 401 con token falso, logout 204 + token antiguo 401, móvil sin scroll horizontal. Tarea 6: `EmployeesView` con `v-for` + `:key`, `onMounted`, `watch` con debounce de 300 ms, descarte de respuestas desordenadas (contador de peticiones), paginación con `meta` de Laravel, estado vacío y de error, `meta.roles` en la ruta + comprobación en el guard, enlace del menú solo para admin/manager. Verificado: admin ve 23 (2 páginas), manager solo su departamento, employee redirigido a inicio, 5 teclas = 1 petición, tabla desplazable en móvil. Tarea 7: `EmployeeFormView` único para alta y edición (`props: true`, `defineProps`, `Promise.all`, `novalidate`), errores 422 por campo con `aria-invalid`/`aria-describedby`, baja lógica con `confirm()`, botones Nuevo/Editar solo para admin. Backend: mensajes de validación en español (`lang/es/validation.php`, `lang/es.json`, `APP_LOCALE=es` en Compose), 58 tests en verde. Verificado: 422 vacío y email duplicado, alta, edición, baja (etiqueta roja), manager sin acceso al formulario. Tarea 8: `LeaveRequestsView` (saldo, solicitud con errores de negocio 422 por campo, listado con filtro por estado, aprobar con rechazo por `prompt()` y cancelar con `confirm()`), botones según `can` de la API (D-019), `GET /api/leave-types` nuevo, componente `AppPagination` (props + emit) y `utils/format.js`. 60 tests de backend. Verificado con los 3 roles: solape rechazado, saldo actualizado en cada paso (aprobar, rechazar, cancelar devuelve días), nadie se aprueba a sí mismo, cancelar una ausencia ya empezada muestra el 422 del servicio, móvil sin scroll. Tarea 9 (26-nov, rama `feat/m6-nominas-frontend`): `PayrollPeriodsView` (solo admin: crear periodo con 422 de duplicado, generar/recalcular y cerrar con `confirm()`, `Object.assign` con la respuesta en vez de recargar), `PayslipsView` (filtro `?period_id=` en la URL observado con `watch` + `immediate`), `PayslipDetailView` (snapshot y líneas devengo/deducción) y descarga del PDF como blob con axios (`api/payslips.js`: el token no viaja en un `<a href>`; el error 503 llega como blob y se lee con `.text()`). `formatMoney` con `Intl.NumberFormat`. Verificado: admin crea, genera y descarga; empleado solo ve periodos cerrados, 403 en nómina ajena y redirección desde /payroll-periods. Tarea 10: dashboard. Backend `GET /api/dashboard` (controlador invocable `__invoke`, KPIs a `null` si no aplican al rol, `withCount`/`withSum`), scope local `LeaveRequest::visibleTo($user)` con `#[Scope]` reutilizado por el listado de ausencias (DRY), 4 tests nuevos (90 en verde). Frontend: tarjetas enlazadas en `HomeView` con grid `auto-fit`. Verificado con los 3 roles | Pendiente: responder si `required` en el formulario hace innecesaria la validación del `AuthController`. Revisar el código de las tareas 4 y 5 (guía de revisión en el chat del 25-nov). 503 del PDF no probado en navegador (sí en tests de Laravel). Errores vistos: etiqueta de cierre suelta fuera de `<template>`. Instalar extensión *Vue - Official* en Cursor para el resaltado |
| M7 Testing y seguridad | PHPUnit/Pest, tests de integración, XSS/CSRF/SQLi/IDOR | **Completado (26-nov, rama `feat/m7-seguridad`).** T1: rate limiting (`login` 5/min por email+IP y 20/min por IP, `api` 60/min por usuario), D-024. T2: CORS cerrado a `CORS_ALLOWED_ORIGINS` y middleware global `SecurityHeaders` (nosniff, DENY, CSP, no-store), D-025. T3: repaso OWASP Top 10 en `docs/SECURITY.md`; huecos corregidos: la baja (DELETE o PUT) revoca tokens vía hook `updated` y bloquea el login, tokens caducan a 480 min, Symfony 7.4.24 (5 CVE), D-026. T4: `ProtectedRoutesTest` (401 en las 32 rutas) y Vitest (8 tests: formato y store de auth), D-027. 102 tests de backend en verde; verificado con curl (429, cabeceras, CORS). Tres hallazgos de la revisión automática de seguridad atendidos | No probado en navegador tras los cambios (el proxy de Vite evita CORS). Pendiente M8: `trustProxies` tras nginx, `APP_DEBUG=false`, CSP del frontend |
| M8 Despliegue | Docker Compose, entornos, secretos, documentación | **Completado (27-nov, rama `feat/m8-docker-docs`).** T1: Dockerfile multi-stage del frontend + nginx (SPA y proxy `/api`). T2: entrypoint idempotente (vendor, `.env`, `APP_KEY`, migrate, seed con `SEED_DEMO_DATA`). T3: arranque verificado en un clon limpio (login y PDF por `:8080`; el reinicio no vuelve a sembrar). T4: `docs/openapi.yaml` validado con Redocly + Swagger UI en `/docs`, D-029. T5: 9 capturas con Playwright; se corrigió el desbordamiento horizontal (menú en móvil, `display:flex` en un `td`, `.visually-hidden` absoluto que se escapaba de la tabla). T6: README. Extra: `trustProxies` + `X-Forwarded-For` sobrescrito por nginx (verificado en la clave del rate limiter), D-028 | Errores vistos: Python en Windows lee y escribe en cp1252 si no se indica `encoding` (un cambio del entrypoint no se aplicó y `.env.example` quedó en Latin-1; lo detectó la revisión de seguridad). Pendiente: despliegue en vivo, CI, test que compare OpenAPI con las rutas |

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

- [ ] Por qué se calcula en céntimos enteros y cómo se redondea
- [ ] Qué es el patrón Strategy y qué principio SOLID cumple `PayrollRule`
- [ ] Qué pasaría al generar nóminas sin transacción y para qué se bloquea el periodo
- [ ] Por qué un periodo cerrado no cambia y qué guarda el snapshot
- [ ] Por qué el empleado no ve borradores y el manager no ve salarios de su equipo
- [ ] Qué es el problema N+1 y cómo lo evita `with()`

- [ ] Por qué Node no recalcula importes y por qué viajan como texto
- [ ] Qué es un timing attack y por qué se compara el token con `timingSafeEqual`
- [ ] Qué significa "fallo cerrado" (el servicio no arranca sin token)
- [ ] Por qué el servicio `pdf` no publica puerto y cómo lo encuentra `app` (`http://pdf:3000`)
- [ ] Qué es un stream y por qué `doc.pipe(res)` no carga el PDF entero en memoria
- [ ] Qué es un ReDoS y cómo lo evita acotar la longitud de la entrada
- [ ] Por qué la Policy va antes de llamar a Node (y cómo lo prueba `Http::assertNothingSent`)
- [ ] Qué aporta `Http::fake()` y qué no prueba (el servicio real)

- [ ] Qué es la fuerza bruta y por qué el límite del login se aplica por email+IP y no solo por IP
- [ ] Qué significan el código 429 y la cabecera `Retry-After`
- [ ] Dónde guarda Laravel los contadores del rate limiting y qué cambia con varias instancias
- [ ] Qué es CORS, quién lo aplica (el navegador, no el servidor) y por qué `*` es mala idea
- [ ] Qué es una petición preflight (`OPTIONS`)
- [ ] Qué es el clickjacking y cómo lo evitan `X-Frame-Options` y `frame-ancestors`
- [ ] Por qué los datos personales llevan `Cache-Control: no-store`
- [ ] Recorrer el OWASP Top 10 con `docs/SECURITY.md` y saber señalar el código de cada fila
- [ ] Por qué la baja de un empleado debe revocar sus tokens y no solo cambiar un estado
- [ ] Por qué el login responde igual a "no existe" y a "dado de baja" (enumeración de usuarios)
- [ ] Qué hacen `composer audit` y `npm audit` y cómo se corrige un aviso sin romper nada
- [ ] Qué es un mock (`vi.mock`) y por qué el test del store no hace peticiones reales
- [ ] Por qué un test que recorre todas las rutas protege mejor que uno por ruta
- [ ] Por qué `formatMoney('1234.50')` da "1234,50 €" y no "1.234,50 €"

- [ ] Qué es un build multi-stage y por qué la imagen final no lleva Node
- [ ] Qué hace nginx como proxy inverso y por qué con el mismo origen no hace falta CORS
- [ ] Por qué una SPA con history mode necesita `try_files ... /index.html`
- [ ] Qué significa que un entrypoint sea idempotente y cómo lo consigue el nuestro
- [ ] Por qué los datos demo van detrás de un flag explícito (`SEED_DEMO_DATA`)
- [ ] Qué es `X-Forwarded-For`, qué es un proxy de confianza y por qué nginx la sobrescribe en vez de añadir
- [ ] Qué es OpenAPI y qué ventajas e inconvenientes tiene escribirlo a mano
- [ ] Por qué un elemento `position: absolute` puede escaparse de un contenedor con `overflow: auto`

## Notas
- Dedicación estimada: ~6 h/día.
