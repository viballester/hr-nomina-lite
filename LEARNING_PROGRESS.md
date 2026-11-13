# LEARNING_PROGRESS

Se actualiza al final de cada módulo. Marcar solo lo **implementado y verificado**.

## Estado por módulo

| Módulo | Conceptos a aprender | Tareas completadas | Pendiente / dudas |
|---|---|---|---|
| M0 Setup y Git | Flujo de ramas, commits convencionales, `.env`, Docker básico | Estructura de carpetas y docs creada (Paso 3). Identidad de Git, `git init`, `.gitattributes` y primer commit verificados. Versiones comprobadas: Git 2.53, PHP 8.3.30, Composer 2.9.5, Node 24.13, npm 11.8. Repo público en GitHub y primer Pull Request. WSL2 y Docker Desktop. Laravel 13 creado en contenedor. Compose con PHP y MySQL 8.4; migraciones verificadas desde un clon limpio de GitHub | Repasar: `DB_HOST=mysql` responde a la red interna de Compose (confundido con `--no-reload`). Primera vez con Docker: seguir explicando los conceptos antes de preguntar |
| M1 BD y modelado | Normalización, FKs, índices, relaciones Eloquent, migraciones, seeders | 15 migraciones (11 tablas de negocio + `users.role`), con `down()` probado. FK con RESTRICT/CASCADE/SET NULL e índices únicos compuestos verificados con datos. 11 modelos con relaciones, casts (`decimal:2`, `boolean`, `array`, `date`) y Fillable (protección de asignación masiva comprobada). Factories de empleado y contrato con Faker `es_ES`. Seeder: 5 puestos, 4 departamentos, 3 tipos de ausencia, 3 usuarios demo por rol y 20 empleados con contrato y saldo | Errores repetidos que vigilar: métodos encadenados en orden incorrecto (`unique()` tras `constrained()`), erratas que fallan en silencio (`constraint()`), `index` vs `unique`, imports `use` olvidados, mayúsculas en nombres de clase (Linux), `'null'` con comillas, sintaxis de arrays asociativos. Sin datos demo de ausencias ni nóminas (M3/M4). Solo Recursos Humanos tiene responsable |
| M2 Auth y API base | MVC, REST, Form Requests, Resources, Sanctum, Policies | Sanctum instalado (`install:api`), login/logout/me con tokens. CRUD de empleados, departamentos y puestos con paginación, búsqueda (`whereHas`, bindings) y baja lógica. `EmployeePolicy`/`DepartmentPolicy`/`PositionPolicy` con `before()` para el admin, filtro de listado por departamento para managers e IDOR cerrado (un `employee` ya no puede ver fichas ajenas). Respuestas JSON coherentes en 401/403/404/422 (`bootstrap/app.php`). 30 tests de integración (Sanctum::actingAs, RefreshDatabase + SQLite, `#[DataProvider]`) | Pendiente repasar: diferencia entre autenticación (401) y autorización (403); por qué `ignore()` hace falta en `update` y no en `store`; por qué una Policy no filtra listas por sí sola. Errores repetidos: `use` olvidados en controladores/Requests (`Gate`, Resources, Requests, modelos), PATCH/PUT confundidos con la ruta sin id, `phpunit.xml` con `<env>` en vez de `<server>` para forzar SQLite cuando Docker fija `DB_CONNECTION` por variable de entorno. CRUD de contratos aplazado a M3/M4 |
| M3 Ausencias | Service layer, transacciones, reglas de negocio, SOLID | — | — |
| M4 Nóminas | Strategy, precisión monetaria, tests unitarios | — | — |
| M5 Microservicio Node | Express, pdfkit, integración entre servicios, tokens internos | — | — |
| M6 Frontend Vue | Componentes, Pinia, Router con guards, formularios, consumo de API | — | — |
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

## Notas
- Dedicación estimada: ~6 h/día.
