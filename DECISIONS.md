# DECISIONS

Registro de decisiones técnicas (formato ligero ADR). Añadir una entrada nueva por decisión; no borrar las antiguas, marcarlas como reemplazadas.

## D-001 · Temática: RR. HH. + nóminas simplificadas + ausencias con aprobación (2026-11-04)
**Decisión:** combinar A (empleados y nóminas) con C (vacaciones y ausencias).
**Por qué:** encaja con consultoras de nóminas/RR. HH., es abarcable en el plazo y permite mostrar reglas de negocio reales (saldos, solapes, aprobación).
**Consecuencia:** facturación queda fuera. Nómina = simulación sin validez legal.

## D-002 · Laravel como backend principal (2026-11-04)
**Por qué:** requisito del proyecto; ofrece Eloquent, migraciones, Sanctum, Policies y testing integrados.

## D-003 · Node.js solo como microservicio de PDF (2026-11-04)
**Por qué:** caso real de servicio aislado, sin duplicar lógica ni acceder a la BD. Solo Laravel lo llama, con token compartido.
**Alternativa descartada:** API paralela en Node (duplicaría funcionalidad y complejidad).

## D-004 · pdfkit en vez de Puppeteer (2026-11-05)
**Decisión del usuario.**
**Por qué:** más ligero, rápido de montar, imagen Docker pequeña. Puppeteer da PDFs más vistosos desde HTML pero pesa (Chromium) y consume tiempo en un plazo ajustado.
**Revisar si:** sobra tiempo y se quiere un diseño de recibo más elaborado.

## D-005 · Importes monetarios sin float (2026-11-04)
**Decisión:** `DECIMAL` en BD (o céntimos enteros); nunca `float`.
**Por qué:** evitar errores de redondeo en nóminas.

## D-006 · Snapshot de nómina (2026-11-04)
**Decisión:** `payslips.snapshot` (JSON) guarda los datos usados al cerrar el periodo.
**Por qué:** una nómina cerrada no debe cambiar si luego cambia el contrato.

## D-007 · Frontend en Vue 3 con CSS propio (2026-11-04)
**Por qué:** evitar gastar tiempo en librerías de UI; el objetivo es demostrar componentes, estado y consumo de API.

## D-008 · Versiones: Laravel 13, PHP 8.3 y MySQL 8.4 LTS (2026-11-05)
**Decisión del usuario.** Sustituye a las versiones Laravel 11 y MySQL 8 previstas inicialmente.
**Por qué:** Laravel 11 no recibe parches de seguridad desde el 12-mar-2026. PHP 8.3 coincide con la versión local y cumple el requisito de Laravel 13. MySQL 8.4 es la rama LTS.

## D-009 · Entorno de desarrollo con `artisan serve --no-reload` (2026-11-05)
**Decisión:** en M0, Compose levanta solo `app` (PHP) y `mysql`. Las credenciales se definen en el `.env` de la raíz y Compose las pasa a ambos servicios. nginx y php-fpm quedan para M8.
**Por qué `--no-reload`:** sin esa opción, `artisan serve` descarta las variables de Docker y lee `backend/.env`. Comprobado: las peticiones web usaban SQLite y `artisan migrate` usaba MySQL, sin ningún error visible.

## D-010 · Extensión `intl` e `icu-data-full` en la imagen PHP (2026-11-05)
**Por qué:** Laravel la necesita para dar formato a números (por ejemplo, `db:show`), y sin `icu-data-full` Alpine no incluye los datos de `es_ES` para los importes.
**Coste:** la imagen pasa de 153 MB a 220 MB. Revisar en M8 al preparar la imagen final.

## D-011 · Tabla `positions` en vez de `job_title` (2026-11-06)
**Decisión del usuario.**
**Por qué:** evita repetir el nombre del puesto en cada empleado y da soporte al CRUD de puestos del MVP.

## D-012 · Reglas de borrado de las claves foráneas (2026-11-06)
**Decisión del usuario.** `payslip_lines.payslip_id`: CASCADE. `payslips.employee_id`: RESTRICT. `departments.manager_id`: SET NULL.
**Por qué:** las líneas no tienen sentido sin su nómina, un empleado con nóminas no debe poder borrarse y un departamento puede quedarse sin responsable.
**Consecuencia:** `manager_id` es nullable y su FK se añade en una migración posterior a `employees`, para resolver la dependencia circular.

## D-013 · Reglas de ausencias en `LeaveRequestService` (2026-11-13)
**Decisión:** controlador fino; días laborables, solapes, saldo y transiciones de estado viven en el servicio. Los errores de negocio se lanzan como `ValidationException` (422).
**Por qué:** las reglas se prueban sin HTTP y se reutilizan (el seeder crea las ausencias demo con el servicio). `ValidationException` da al frontend un único formato de error sin registrar excepciones propias; alternativa descartada por coste en el MVP.

## D-014 · Saldo: los pendientes reservan días y se descuenta al aprobar (2026-11-13)
**Decisión:** disponible = `days_total - days_used - pendientes`. `days_used` se incrementa al aprobar (o al crear si el tipo no requiere aprobación) y se devuelve al cancelar una aprobada.
**Por qué:** evita que dos solicitudes pendientes gasten el mismo saldo. Cada cambio de saldo va en transacción con `lockForUpdate` sobre la solicitud y el saldo.
**Límites conocidos:** sin festivos ni medios días; no se admiten solicitudes que crucen de año; si cambiara `deducts_balance` de un tipo con solicitudes aprobadas, la cancelación devolvería días según el valor nuevo (no hay endpoint que lo permita).

## D-015 · Autorización de ausencias (2026-11-13)
**Decisión:** aprueba/rechaza el manager del departamento o el admin, nunca el propio solicitante; cancela solo el propio empleado (pendiente o aprobada no empezada). El listado se filtra en la consulta con `match` por rol y `default => 403`.
**Por qué:** la Policy decide sobre un objeto, no filtra listas; el filtro cerrado por defecto evita que un rol desconocido herede la visibilidad del admin.

## D-016 · Frontend en JavaScript, Vite en local y proxy `/api` (2026-11-16)
**Decisión:** Vue sin TypeScript; en desarrollo Vite corre fuera de Docker (`npm run dev`) y reenvía `/api` a `localhost:8000`.
**Por qué:** TypeScript no cabe en el plazo; Vite local da recarga instantánea (el contenedor `frontend` llega en M8); el proxy deja un solo origen y evita configurar CORS en desarrollo (CORS se trata en M7).

## D-017 · Token Sanctum en `localStorage` con interceptor de Axios (2026-11-16)
**Decisión:** el store `auth` guarda el token en `localStorage`; un interceptor de petición añade `Authorization: Bearer` leyendo de `localStorage` (no del store, para evitar la dependencia circular store ↔ http).
**Por qué:** simple y coherente con la API por tokens de M2. Riesgo conocido: un XSS podría leer el token. Alternativa (Sanctum SPA con cookies `HttpOnly`) descartada en el MVP; se documenta en M7.

## D-018 · Mensajes de validación de la API en español (2026-11-26)
**Decisión:** `APP_LOCALE=es` (Compose y `.env.example`) con `lang/es/validation.php` y `lang/es.json` traducidos a mano solo para las reglas que usa la API; el resto cae en inglés (`APP_FALLBACK_LOCALE=en`).
**Por qué:** el frontend pinta los mensajes 422 tal cual debajo de cada campo. Se evita añadir un paquete de traducciones (`laravel-lang`) por una docena de mensajes. Ningún test depende del texto de los mensajes (58 en verde tras el cambio).

## D-019 · Permisos por recurso en la respuesta (`can`) (2026-11-26)
**Decisión:** `LeaveRequestResource` devuelve `can: { review, cancel }` calculado con `$request->user()->can(...)`, es decir, con la misma `LeaveRequestPolicy` que protege los endpoints. El frontend muestra Aprobar/Rechazar/Cancelar según esos valores.
**Por qué:** evita copiar en Vue reglas como "nadie se aprueba a sí mismo" o "el manager solo revisa su departamento": una sola fuente de verdad. Las reglas de estado (solo pendientes, que no haya empezado) siguen en el servicio y llegan como 422 si el usuario lo intenta. Alternativa descartada: duplicar las reglas en el frontend.

## D-020 · Cálculo de nómina con reglas (Strategy) en céntimos enteros (2026-11-26)
**Decisión:** cada concepto implementa `PayrollRule`; `PayrollCalculator` aplica primero los devengos y después las deducciones sobre el bruto. Se calcula en céntimos (`int`), los porcentajes en puntos básicos y se redondea cada línea al céntimo (mitades hacia arriba). Concreta D-005.
**Reglas ficticias:** salario base; 30,00 € por trienio; IRPF simulado 15 %; cotización simulada 6,35 %.
**Por qué:** un concepto nuevo es una clase nueva (abierto/cerrado) y se prueba sin base de datos. Alternativa descartada: un único método con todas las fórmulas.
**Límites:** sin prorrateo de meses incompletos, pagas extra ni importes negativos.

## D-021 · Generación y cierre de periodos en `PayrollService` (2026-11-26)
**Decisión:** un periodo en borrador puede regenerarse (borra sus nóminas y las recalcula); uno cerrado no cambia. Generar y cerrar van en transacción con `lockForUpdate` sobre el periodo. El snapshot (D-006) se guarda al generar y queda congelado al cerrar. Entran los empleados activos con contrato vigente algún día del mes; con varios, el más reciente. La antigüedad se cuenta a fin de mes.
**Por qué:** permite corregir datos antes del cierre sin duplicar nóminas.
**Límites:** sin prorrateo; un empleado dado de baja lógica durante el mes no genera nómina.

## D-022 · Acceso a las nóminas (2026-11-26)
**Decisión:** los periodos solo los gestiona el admin. Cada empleado, también el manager, ve únicamente sus nóminas y solo de periodos cerrados; el admin ve todas, incluidos los borradores.
**Por qué:** el salario es un dato sensible y un borrador puede cambiar. El listado filtra en la consulta (cerrado por defecto) y la ficha usa la Policy (evita IDOR).

## D-023 · Contrato y seguridad del pdf-service (2026-11-26)
**Decisión:** Laravel envía a `POST /payslips/pdf` un JSON con los datos del snapshot y los importes como texto `"0.00"` (cast `decimal:2`); Node valida (422), solo formatea y nunca suma. Token `Bearer` compartido (`PDF_SERVICE_TOKEN`) comparado en tiempo constante; sin token el servicio no arranca. Sin puerto publicado: solo accesible en la red de Compose. Laravel autoriza con la misma Policy que `show` **antes** de llamar a Node y traduce caídas, timeouts (10 s) o errores 5xx a un 503 (`PdfServiceUnavailableException`).
**Por qué:** una sola fuente de verdad para importes y permisos (D-003, D-005, D-022); si Node cae, solo falla la descarga.
**Detalles:** validación escrita a mano (pocos campos, sin Zod/Joi); importes acotados a 10 dígitos enteros tras una revisión de seguridad (ReDoS); tests de Node con `node:test` (sin Jest) y de Laravel con `Http::fake()`.
**Límites:** sin paginación del recibo (máx. 50 líneas); sin caché de PDFs, se generan en cada descarga.

## Pendientes de decidir
- Pest vs PHPUnit (M7).
- Herramienta de documentación de API: Scribe vs OpenAPI manual (M8).
- Despliegue en vivo (Render/VPS) o solo Docker + README (M8).
