# Seguridad: repaso OWASP Top 10 (2021)

Revisión del código realizada en M7 (26-nov-2026). Cada fila indica dónde está la protección y qué test la demuestra.

| # | Riesgo | Cómo se mitiga en la app | Dónde / test |
|---|---|---|---|
| A01 | Control de acceso roto (IDOR, escalada) | Policies en todos los recursos; listados filtrados "cerrados por defecto" con scopes; la API devuelve `can` y Vue solo oculta botones (la decisión es del servidor). Al dar de baja a un empleado se revocan sus tokens y no puede volver a entrar. | `app/Policies/*`, `EmployeeApiTest` (empleado 3 no ve la ficha 2, baja revoca tokens), `PayrollApiTest`, `LeaveReviewApiTest` |
| A02 | Fallos criptográficos | Contraseñas con bcrypt (cast `hashed`); `password` y `remember_token` ocultos en JSON; tokens Sanctum guardados como hash SHA-256; token Node comparado en tiempo constante. | `User` (`#[Hidden]`), `services/pdf-service/src/requireToken` |
| A03 | Inyección (SQL, XSS) | Eloquent/Query Builder con parámetros enlazados; el único `selectRaw` no lleva datos del usuario. Vue escapa por defecto y no se usa `v-html`. CSP `default-src 'none'` en la API. | `PayslipController`, `SecurityHeaders` |
| A04 | Diseño inseguro | Reglas de negocio en Services con transacciones y `lockForUpdate` (saldo de ausencias, generación de nóminas); nadie se aprueba a sí mismo; periodo cerrado inmutable. | `LeaveRequestService`, `PayrollService`, tests de servicio |
| A05 | Configuración insegura | CORS limitado a `CORS_ALLOWED_ORIGINS`; cabeceras de seguridad en todas las respuestas; errores JSON sin traza fuera de `APP_DEBUG`; `pdf` sin puerto publicado. **Pendiente M8:** `APP_DEBUG=false` y `APP_ENV=production` en el despliegue. | `config/cors.php`, `SecurityHeadersAndCorsTest` |
| A06 | Componentes vulnerables | Dependencias con lockfile (`composer.lock`, `package-lock.json`). `composer audit` encontró 5 avisos en Symfony (http-kernel, routing, mailer), corregidos subiendo a 7.4.24; `npm audit` sin avisos. | `composer.lock` |
| A07 | Fallos de autenticación | Rate limiting del login (5/min por email+IP, 20/min por IP); mismo mensaje para email inexistente, contraseña errónea o cuenta dada de baja; tokens caducan a las 8 h (`SANCTUM_EXPIRATION`); logout revoca el token. | `AppServiceProvider`, `RateLimitTest`, `EmployeeApiTest` |
| A08 | Integridad de software y datos | Mass assignment controlado con `#[Fillable]` + `$request->validated()`; `role` no es asignable en masa. El snapshot de la nómina no cambia al cerrar el periodo. | `app/Models/*`, `app/Http/Requests/*` |
| A09 | Registro y monitorización | Logs de Laravel (`storage/logs`); errores del servicio PDF registrados. No hay auditoría de cambios (ampliación opcional 3). | — |
| A10 | SSRF | Laravel solo llama a una URL fija de configuración (`PDF_SERVICE_URL`); ningún dato del usuario forma la URL. | `PayslipPdfClient` |

## Riesgos aceptados

- **Token en `localStorage`:** expuesto si hubiera XSS. Se acepta porque Vue escapa la salida y no hay `v-html`; la alternativa (cookies `HttpOnly` con Sanctum SPA) se descarta por plazo.
- **Enumeración por tiempo en el login:** si el email no existe no se ejecuta `Hash::check`, y la respuesta es algo más rápida. Mitigado por el rate limiting.
- **Tokens caducados en la base de datos:** dejan de valer, pero no se borran. Se podría programar `sanctum:prune-expired` cuando haya scheduler.
