# LEARNING_PROGRESS

Se actualiza al final de cada módulo. Marcar solo lo **implementado y verificado**.

## Estado por módulo

| Módulo | Conceptos a aprender | Tareas completadas | Pendiente / dudas |
|---|---|---|---|
| M0 Setup y Git | Flujo de ramas, commits convencionales, `.env`, Docker básico | Estructura de carpetas y docs creada (Paso 3). Identidad de Git, `git init`, `.gitattributes` y primer commit verificados. Versiones comprobadas: Git 2.53, PHP 8.3.30, Composer 2.9.5, Node 24.13, npm 11.8. Repo público en GitHub y primer Pull Request. WSL2 y Docker Desktop. Laravel 13 creado en contenedor. Compose con PHP y MySQL 8.4; migraciones verificadas desde un clon limpio de GitHub | Repasar: `DB_HOST=mysql` responde a la red interna de Compose (confundido con `--no-reload`). Primera vez con Docker: seguir explicando los conceptos antes de preguntar |
| M1 BD y modelado | Normalización, FKs, índices, relaciones Eloquent, migraciones, seeders | — | — |
| M2 Auth y API base | MVC, REST, Form Requests, Resources, Sanctum, Policies | — | — |
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

## Notas
- Dedicación estimada: ~6 h/día.
