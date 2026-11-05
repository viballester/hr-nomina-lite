# PROJECT_CONTEXT

## Qué es

Plataforma web de **gestión de RR. HH. y nóminas simplificada** (temática A) con **vacaciones y ausencias con flujo de aprobación** (temática C). Proyecto de portafolio dirigido a consultoras de software de nóminas, RR. HH. y facturación. Nombre de trabajo: *HR Nómina Lite*.

> **Aviso:** todos los datos son ficticios. El cálculo de nóminas es una **simulación didáctica sin validez legal** (no implementa IRPF ni Seguridad Social reales). Debe indicarse en la interfaz y en el README.

## Usuarios y roles

- `admin`: gestiona empleados, contratos, periodos de nómina y configuración.
- `manager`: aprueba o rechaza ausencias de su departamento.
- `employee`: consulta sus datos, solicita ausencias, ve su saldo y sus nóminas.

## Funcionalidades MVP

Auth con roles · CRUD empleados/departamentos/contratos · Ausencias con saldo y aprobación · Nómina mensual simplificada con snapshot · Recibo PDF · Dashboard (4–5 KPIs) · Tests, API documentada, Docker, README.

## Stack

| Capa | Tecnología |
|---|---|
| Frontend | Vue 3, Vite, Vue Router, Pinia, Axios, CSS propio responsive |
| Backend | PHP 8.3 + Laravel 13 (API REST, Sanctum), arquitectura MVC + capa Services |
| Microservicio | Node.js + Express + **pdfkit** (solo genera PDF) |
| BD | MySQL 8.4 LTS |
| Infra | Docker + Docker Compose (nginx, php-fpm, mysql, node-pdf, frontend) |
| Tests | PHPUnit/Pest, test del servicio Node; opcional Playwright |

## Restricciones

- Fecha límite 30-nov-2026 (cierre objetivo 28-nov). ~6 h/día.
- Prioridad: calidad y coherencia del MVP sobre cantidad de funcionalidades.
- Metodología: un chat por módulo; aprendizaje activo (explicar, guiar, revisar). Ver `CLAUDE.md`.

## Decisiones tomadas por el usuario

- Temática A + C validada.
- Alcance MVP validado (5-nov-2026).
- PDF con **pdfkit** (no Puppeteer).
