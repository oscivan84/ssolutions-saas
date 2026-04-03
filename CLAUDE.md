# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SSolutions: Multi-tenant SaaS support ticket system with WhatsApp bot, AI microservice, event system, and job queue. Manages support tickets, automates PC diagnostics via Python agent, communicates with clients via WhatsApp, and uses AI (Anthropic Claude / OpenAI) via a centralized FastAPI microservice for sales-oriented bot responses, summaries, client scoring, and technician suggestions.

## Setup

```bash
# 1. Database: import schema into MySQL (database: dbsolventas17)
mysql -u root -p dbsolventas17 < sql/soporte_tables.sql
# For existing installations, run migrations in order:
mysql -u root -p dbsolventas17 < sql/migration_multitenant.sql
mysql -u root -p dbsolventas17 < sql/migration_advanced.sql

# 2. Copy and configure environment
cp .env.example .env
# Edit .env with DB credentials

# 3. AI Microservice (FastAPI)
cd ai-service && pip install -r requirements.txt
python api.py  # Runs on http://127.0.0.1:8100

# 4. Job Worker (cron every minute or daemon)
# crontab: * * * * * php /path/to/landingV2/api/worker.php
# or: while true; do php api/worker.php; sleep 5; done

# 5. Python agent (on client machines)
cd agenteSoporte && pip install -r requirements.txt
python agente.py
```

No build tools or package manager — PHP files are served directly. Views are loaded into the parent SSolutions AdminLTE template via `cargarContenido('soporte_dashboard')`.

## Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  PHP App (CRUD)  │───>│  AI Microservice  │    │  Python Agent    │
│  AdminLTE Panel  │<───│  FastAPI :8100    │    │  (client PCs)    │
│  WhatsApp flows  │    │  prompts/logic/   │    └────────┬─────────┘
└───────┬──────────┘    └──────────────────┘             │
        │                                         POST /api/diagnostico.php
        ├── config/tenant.php  (multi-tenant)            │
        ├── services/EventService.php (events)     ┌─────▼──────────┐
        ├── services/JobQueue.php (async jobs)     │  MySQL          │
        └── api/worker.php (cron job processor)    │  dbsolventas17  │
                                                   └─────────────────┘
```

**Modular Multi-Tenant Architecture (LEGO):**

```
Core (siempre activo):
  config/         — Database, Tenant, Security, AI/WhatsApp config
  services/       — AIService, WhatsAppService, ConversacionService, FlowEngine,
                    EventService, JobQueue, NotificacionService, AuditService, LogService
  modules/        — ModuleLoader + definiciones por nicho
  ai-service/     — FastAPI microservice (Python)

Modules (por tipo de negocio):
  modules/base/       — Core compartido
  modules/tecnicos/   — Diagnósticos, mantenimientos, repuestos
  modules/estetica/   — Citas, recordatorios, promos

Data + Views:
  model/   — Data access (ALL queries filtered by Tenant::id())
  ajax/    — Request handlers
  api/     — REST endpoints + worker.php
  view/    — Bootstrap + jQuery + DataTables panels
  sql/     — Schema + 5 migrations
```

**Key architecture decisions:**
- `ModuleLoader` loads services/views/prompts dynamically based on `negocios.tipo_negocio`
- `FlowEngine` drives conversations from JSON (table `flujos_conversacion`) instead of hardcoded switch
- `servicios_negocio` table replaces hardcoded ENUM for service types
- ConversacionService tries FlowEngine first, falls back to legacy switch for backward compat
- New niches (estética, automotriz, etc.) = new module definition + new flujo JSON + new servicios. Zero core code changes.

### AI Microservice (`ai-service/`)

**Separate Python service** — PHP calls it via HTTP instead of calling Anthropic/OpenAI directly.

```
ai-service/
├── api.py              # FastAPI app — 6 endpoints
├── config.py           # Service configuration
├── requirements.txt    # fastapi, anthropic, openai, uvicorn
├── prompts/
│   └── templates.py    # All prompt templates centralized (ventas, diagnostico, scoring...)
└── logic/
    ├── providers.py    # Anthropic + OpenAI unified interface
    └── fallbacks.py    # Rule-based fallbacks (no API needed)
```

**Endpoints:**
| Method | Path | Purpose |
|--------|------|---------|
| POST | `/responder` | WhatsApp bot response (sales-oriented) |
| POST | `/diagnostico` | PC diagnostic summary |
| POST | `/recomendaciones` | Technical recommendations (JSON) |
| POST | `/clasificar` | Intent classification |
| POST | `/score-cliente` | Client scoring: caliente/tibio/frio |
| POST | `/sugerencia-tecnico` | Technician suggestions (what to say/do/upsell) |
| GET | `/health` | Health check |

**Triple fallback on every endpoint:** quick rules → local fallback → API call.
**Auth:** `X-Internal-Key` header. PHP sends AI config (provider/key/model) per request per negocio.

### Event System (`services/EventService.php`)

Decoupled event-driven architecture. Events persist in `eventos` table and trigger automatizations.

```php
EventService::emit('ticket_creado', ['idticket' => 1, 'codigo' => 'TKT-001', 'idpersona' => 5]);
EventService::emit('estado_cambiado', ['idticket' => 1, 'estado' => 'en_proceso']);
EventService::emit('mensaje_recibido', ['telefono' => '573001234567']);
```

Events automatically check `automatizaciones` table → create jobs (immediate or delayed).

### Job Queue (`services/JobQueue.php` + `api/worker.php`)

Simple MySQL-based async job queue. No Redis needed.

```php
JobQueue::crear('enviar_whatsapp', ['telefono' => '...', 'mensaje' => '...']);
JobQueue::crear('scoring', ['idpersona' => 5], 'alta', 60); // 60 min delay
```

**Worker** (`api/worker.php`): processes pending jobs via cron. Handles: `enviar_whatsapp`, `scoring`, `alerta`, `generar_resumen_ia`.

### Multi-Tenant Architecture

**Core:** `config/tenant.php` — `Tenant` static class resolves `negocio_id` globally.

```
Resolution priority: Session → X-API-Key → X-Negocio-Id → Fallback (first active)
```

**Key methods:** `Tenant::id()`, `Tenant::config()`, `Tenant::negocio()`, `Tenant::tieneFeature()`, `Tenant::puedeCrearTicket()`, `Tenant::rolUsuario()`, `Tenant::tieneRol()`

**Every table has `negocio_id INT NOT NULL`** — data isolation enforced at model layer.

### SaaS Plans

| Plan | Price/mo | Tickets/mo | Techs | WhatsApp | IA | Diagnostics | Automation |
|------|----------|------------|-------|----------|-----|-------------|------------|
| basico | $49,900 | 50 | 3 | - | - | - | - |
| pro | $149,900 | 200 | 10 | Yes | Yes | - | - |
| premium | $299,900 | unlimited | 50 | Yes | Yes | Yes | Yes |

### Ticket State Machine

```
abierto → en_diagnostico → en_proceso → finalizado
                              ↕
                    esperando_repuestos
Any non-final state → cancelado → abierto (reopen)
```

State transitions emit `estado_cambiado` events → trigger automatizations.

### Notifications (`services/NotificacionService.php`)

Real-time notifications displayed in navbar (polling every 30s). Auto-generated by EventService for:
- `cliente_respondio` — WhatsApp message received
- `cliente_caliente` — Hot lead detected by IA scoring
- `ticket_estancado` — Ticket stuck > threshold
- `venta_cerrada` — Ticket finalized

### Security Layer (`config/security.php`)

- **Encryption**: `encriptar()` / `desencriptar()` — AES-256-CBC for API keys in DB. Master key in `.env` (`MASTER_ENCRYPT_KEY`)
- **Rate limiting**: `checkRateLimit($key, $max, $window)` — file-based, per IP. Applied to `/api/diagnostico.php` (30 req/min)
- **Audit log**: `AuditService::log()` — tracks config changes, sensitive operations
- **Auth**: `Tenant::requireAuth('admin')` — enforced on write operations in AJAX handlers

### Onboarding (`view/soporte_onboarding.php`)

4-step wizard for new tenants: Business data → WhatsApp → IA → First ticket. Progress tracked in `onboarding_estado` table. Auto-shows banner if incomplete.

### Mobile-First (`view/componente_notificaciones.php`)

CSS overrides for mobile: large buttons, fixed bottom action bar, responsive DataTables, full-width modals. Include in main template.

### Modular Architecture (`modules/ModuleLoader.php`)

System is nicho-agnostic. Core handles WhatsApp+IA+conversations. Modules define the domain:

```
ModuleLoader::getInstance()
  →  getServicios()            // Dynamic services from servicios_negocio table
  →  getFlujoConversacion()    // JSON conversation flow from flujos_conversacion table
  →  getPromptVentas()         // Prompt by business type from prompts_ia table
  →  getVistasModulo()         // Menu items from module.php definition
  →  tieneModulo('tecnico')    // Check if module active
```

**To add a new niche (zero core code changes):**
1. Create `modules/NICHO/module.php` (services, prompt, vistas)
2. INSERT into `flujos_conversacion` (JSON conversation flow)
3. INSERT into `servicios_negocio` (service catalog)
4. Set `negocios.tipo_negocio = 'NICHO'`

### FlowEngine (`services/FlowEngine.php`)

JSON-driven conversation engine. Replaces hardcoded switch statements:

```json
{"estados": {
  "inicio": {"mensaje": "Hola {nombre}!", "transiciones": {"ACEPTAR": "cotizacion", "default": "esperando"}},
  "cotizacion": {"mensaje": "Costo: {moneda}{precio}", "transiciones": {"ACEPTAR": "confirmado"}},
  "confirmado": {"mensaje": "Confirmado!", "accion": "confirmar_servicio", "es_final": true}
}}
```

ConversacionService tries FlowEngine first → falls back to legacy switch if no JSON flow defined.

## Key Patterns

- **Modular LEGO architecture**: `ModuleLoader` resolves services/flows/prompts per `tipo_negocio`. New niches = config, not code.
- **JSON-driven conversation**: `FlowEngine` reads `flujos_conversacion` table. Transitions, messages, actions all configurable.
- **AI via microservice**: `AIService.php` is HTTP client (10s timeout) to `ai-service/`. Falls back to local PHP.
- **Multi-tenant isolation**: ALL queries use `Tenant::id()` — never query without `negocio_id`
- **Event-driven**: Key actions emit events → notifications + automatizations → jobs → worker processes async
- **Prompts centralized** in `ai-service/prompts/templates.py` — sanitized inputs to prevent prompt injection
- All DB queries use MySQLi prepared statements with param count validation
- Rate limiting on public endpoints, audit logging on sensitive operations
- Jobs auto-recover from stuck state (10 min timeout) with exponential retry backoff

## Database Tables

**Multi-Tenant Core:** `negocios` (+ tipo_negocio), `usuarios_negocio`, `planes_saas`
**Modular:** `modulos_negocio`, `servicios_negocio`, `flujos_conversacion`
**Support:** `diagnosticos`, `tickets`, `historial_estados_ticket`, `mantenimientos`
**WhatsApp:** `mensajes_whatsapp`, `conversaciones_whatsapp`, `plantillas_mensaje`
**Config:** `configuracion_soporte` (scoped by negocio_id)
**Advanced:** `prompts_ia` (+ tipo_negocio), `eventos`, `jobs`, `automatizaciones`, `score_clientes`
**UX:** `notificaciones`, `audit_log`, `onboarding_estado`, `log_sistema`
**Shared (SSolutions):** `persona`, `usuario`, `articulo`, `venta`

## SQL Migrations (run in order)

```bash
mysql -u root -p dbsolventas17 < sql/soporte_tables.sql          # Base schema (fresh install)
mysql -u root -p dbsolventas17 < sql/migration_multitenant.sql    # Multi-tenant (existing DB)
mysql -u root -p dbsolventas17 < sql/migration_advanced.sql       # Events, jobs, automations, scoring, log
mysql -u root -p dbsolventas17 < sql/migration_ux.sql             # Notifications, audit, onboarding
mysql -u root -p dbsolventas17 < sql/migration_modular.sql        # Modular architecture (LEGO)
mysql -u root -p dbsolventas17 < sql/datos_demo.sql               # Demo data (optional)
```

## Environment Variables

`.env`: DB_HOST, DB_USER, DB_PASS, DB_NAME, MASTER_ENCRYPT_KEY
`AI_SERVICE_URL`: URL of FastAPI microservice (default: http://127.0.0.1:8100)
`AI_SERVICE_INTERNAL_KEY`: Internal auth key for AI service
Runtime config in `configuracion_soporte` table per negocio.
