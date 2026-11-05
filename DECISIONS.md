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

## Pendientes de decidir
- Pest vs PHPUnit (M7).
- Herramienta de documentación de API: Scribe vs OpenAPI manual (M8).
- Despliegue en vivo (Render/VPS) o solo Docker + README (M8).
