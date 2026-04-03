# QA CONTEXT — SSolutions SaaS

Documento único de contexto para QA. Contiene TODO lo necesario para probar el sistema.

---

## 1. FLUJO PRINCIPAL QUE GENERA DINERO

```
Agente Python → diagnóstico → ticket auto → WhatsApp al cliente → cliente responde
→ bot clasifica intención → guía a cotización → cliente acepta → técnico asignado
→ servicio realizado → ticket cerrado → convertido a venta
```

**Flujo alternativo (recuperación):**
```
Cliente no responde (24h+) → automatización dispara → job "enviar_whatsapp"
→ mensaje de seguimiento → cliente responde → retoma flujo de venta
```

**Acción que NO puede fallar NUNCA:** Envío de WhatsApp + creación de ticket. Si falla = pierde el cliente.

**Qué es peor (en orden):**
1. No responder al cliente (pierde venta inmediata)
2. Enviar mensaje mal (daño de marca)
3. Perder datos (recuperable con backups)

---

## 2. ARQUITECTURA REAL

### URLs y comunicación
```
PHP App (AdminLTE)     → http://localhost/landingV2/
AI Microservice        → http://127.0.0.1:8100 (FastAPI)
WhatsApp Webhook       → https://dominio.com/landingV2/api/webhook_whatsapp.php
Python Agent           → POST http://dominio.com/landingV2/api/diagnostico.php
```

### Comunicación PHP → FastAPI
- Protocolo: HTTP POST (cURL)
- Timeout conectar: 3s
- Timeout respuesta: 10s
- Auth: Header `X-Internal-Key`
- Si falla: fallback local PHP (regex/reglas), usuario no ve error

### Manejo de errores
| Falla | Comportamiento |
|-------|---------------|
| IA caída | Fallback local (respuestas por regex). Nunca error visible. |
| DB caída | HTTP 500. Error de conexión. Sistema no funciona. |
| WhatsApp falla | Log error. Mensaje queda en estado "fallido". No se reintenta automáticamente. |
| Worker caído | Jobs se acumulan en estado "pendiente". Auto-recovery de stuck jobs > 10min. |

### Entornos
- Solo producción actualmente. Sin staging.
- `.env` para credenciales BD.
- Variables de entorno: `AI_SERVICE_URL`, `AI_SERVICE_INTERNAL_KEY`, `MASTER_ENCRYPT_KEY`
- Config runtime en tabla `configuracion_soporte` por negocio.

---

## 3. BASE DE DATOS

### Tablas (19 total)

**Multi-tenant (3):** `negocios`, `usuarios_negocio`, `planes_saas`
**Soporte (4):** `diagnosticos`, `tickets`, `historial_estados_ticket`, `mantenimientos`
**WhatsApp (3):** `mensajes_whatsapp`, `conversaciones_whatsapp`, `plantillas_mensaje`
**Config (1):** `configuracion_soporte`
**Avanzado (5):** `prompts_ia`, `eventos`, `jobs`, `automatizaciones`, `score_clientes`
**UX (3):** `notificaciones`, `audit_log`, `onboarding_estado`, `log_sistema`
**Compartidas SSolutions (4):** `persona`, `usuario`, `articulo`, `venta`

### Relaciones críticas
```
tickets.negocio_id → negocios.idnegocio (SIN FK explícita)
tickets.idpersona → persona.idpersona (SIN FK)
tickets.idusuario → usuario.idusuario (SIN FK)
tickets.iddiagnostico → diagnosticos.iddiagnostico (SIN FK)
mensajes_whatsapp.idticket → tickets.idticket (SIN FK)
conversaciones_whatsapp.idticket → tickets.idticket (SIN FK)
mantenimientos.idticket → tickets.idticket (SIN FK)
```

**RIESGO:** No hay FOREIGN KEYS. La integridad se mantiene solo por lógica de aplicación.

### Constraints existentes
| Tabla | Constraint |
|-------|-----------|
| negocios | UNIQUE(slug) |
| usuarios_negocio | UNIQUE(idusuario, idnegocio) |
| planes_saas | UNIQUE(codigo) |
| tickets | UNIQUE(codigo_ticket) |
| plantillas_mensaje | UNIQUE(nombre, negocio_id) |
| configuracion_soporte | UNIQUE(clave, negocio_id) |

### Campos sensibles
- `configuracion_soporte.valor` — contiene API keys en texto plano (encriptación disponible pero no forzada)
- `tickets.costo_estimado`, `tickets.costo_final` — DECIMAL(10,2)
- `persona.telefono` — formato variable (10 o 12 dígitos, con o sin +57)
- `tickets.estado` — ENUM con 6 valores, transiciones validadas en PHP

---

## 4. SEGURIDAD Y MULTI-TENANT

### Resolución de negocio_id (flujo real)
```
1. Session: $_SESSION['negocio_id'] (panel admin logueado)
2. X-API-Key header → SELECT negocio_id FROM configuracion_soporte WHERE clave='api_key_agente' AND valor=?
3. X-Negocio-Id header (llamadas internas)
4. Fallback: SELECT idnegocio FROM negocios WHERE estado='activo' ORDER BY idnegocio LIMIT 1
5. Si nada: negocio_id = 0 (log CRITICAL, queries no retornan datos)
```

### Autenticación
- **Panel admin:** Session PHP (`$_SESSION['idusuario']`, `$_SESSION['negocio_id']`)
- **API diagnóstico:** Header `X-API-Key` → resuelve tenant automáticamente
- **Webhook WhatsApp:** Resuelve tenant desde `phone_number_id` del payload Meta
- **AI Microservice:** Header `X-Internal-Key` (valor fijo compartido)

### Endpoints públicos (sin auth)
- `GET /api/webhook_whatsapp.php` (verificación Meta)
- `GET /api/dashboard.php` (stats, solo lectura, filtrado por tenant)

### Roles por acción
| Acción | Rol mínimo |
|--------|-----------|
| Ver tickets/diagnósticos | tecnico |
| Crear/editar ticket | tecnico |
| Registrar mantenimiento | tecnico |
| Cambiar estado ticket | tecnico |
| Modificar configuración | admin |
| Gestionar automatizaciones | admin |
| Ver audit log | admin |

---

## 5. IA

### Prompts actuales

**Ventas (WhatsApp bot):**
- Objetivo: convertir conversación en venta/cita
- Max 80 palabras, amigable, profesional
- Técnicas de cierre: preguntas alternativas, urgencia, valor agregado
- Incluye precios de servicios del negocio

**Diagnóstico:**
- Explicar problemas de PC en lenguaje sencillo
- Max 150 palabras
- Incluir recomendaciones prácticas

**Score cliente:**
- Evalúa: caliente (quiere comprar) / tibio (indeciso) / frío (no interesado)
- Retorna JSON con razón y acción sugerida

### Casos de uso de IA
| Caso | Endpoint | Fallback |
|------|----------|----------|
| Responder WhatsApp | `/responder` | Regex por intención + respuestas fijas |
| Clasificar intención | `/clasificar` | 8 regex patterns |
| Resumen diagnóstico | `/diagnostico` | Template con umbrales CPU/RAM/disco |
| Recomendaciones | `/recomendaciones` | Reglas por umbrales → JSON |
| Score cliente | `/score-cliente` | Retorna "tibio" siempre |
| Sugerencia técnico | `/sugerencia-tecnico` | "Contactar al cliente" genérico |

### Límites
- Timeout PHP → FastAPI: **10 segundos**
- Timeout FastAPI → Anthropic/OpenAI: depende del SDK (default ~60s)
- Rate limit FastAPI: **60 req/min por api_key**
- Rate limit PHP endpoints: **30 req/min por IP**
- Longitud máxima mensaje entrada: sin límite explícito (pero regex solo evalúa primeros caracteres)

---

## 6. WHATSAPP

### Integración
- **Tipo:** Meta Cloud API v18.0
- **Número:** Configurable por negocio (puede ser sandbox o real)
- **Webhook URL:** `https://dominio/landingV2/api/webhook_whatsapp.php`

### Flujo completo
```
Cliente envía mensaje → Meta webhook POST → webhook_whatsapp.php
  → Resolver tenant desde phone_number_id
  → WhatsAppService::procesarWebhook() extrae datos
  → MensajeWhatsApp::Registrar() guarda en BD
  → ConversacionService::procesarMensajeEntrante()
    → Buscar/crear conversación activa (<24h)
    → Clasificar intención (IA o regex)
    → Generar respuesta según estado del flujo
  → WhatsAppService::enviarTexto() o enviarBotones()
  → Siempre retorna HTTP 200 (requisito Meta)
```

### Si falla
- Webhook siempre retorna 200 (Meta no reintenta)
- Si tenant no resuelto: log error, exit silencioso
- Si envío falla: log error, mensaje guardado con estado "fallido"
- Si IA falla: fallback local, mensaje siempre se envía

### Tipos de mensajes
1. **Texto plano** — `enviarTexto()`
2. **Botones interactivos** (max 3) — `enviarBotones()`
3. **Listas** — `enviarLista()`
4. **Plantillas con variables** — `enviarDesdePlantilla()`

### Normalización de teléfono
- Si 10 dígitos y empieza con "3": agrega "57" (Colombia)
- Remueve "+", caracteres no numéricos
- Búsqueda por últimos 10 dígitos con LIKE

---

## 7. AUTOMATIZACIONES + JOBS

### Tipos de jobs
| Tipo | Acción | Timeout |
|------|--------|---------|
| `enviar_whatsapp` | Envía texto o plantilla | N/A (WhatsApp API ~15s) |
| `scoring` | Llama `/score-cliente` → guarda en `score_clientes` | 15s |
| `alerta` | Log en error_log | instantáneo |
| `generar_resumen_ia` | Llama `/diagnostico` | 30s |

### Worker
- Ejecuta: `php api/worker.php`
- Frecuencia recomendada: cron cada minuto (`* * * * *`)
- Max jobs por ejecución: 10
- Auto-recovery: jobs stuck >10min → reset a "pendiente"
- Retry: exponencial (1min, 5min, 15min, 30min, 60min)
- Prioridad: alta > media > baja

### Automatizaciones por defecto
1. **Seguimiento 24h:** Si conversación en "esperando_respuesta" y no responde → enviar WhatsApp (delay: 1440 min)
2. **Ticket estancado:** Si estado = "en_diagnostico" por >2 días → alerta (delay: 2880 min)
3. **Score al crear ticket:** Evaluar cliente inmediatamente (delay: 0)

### Riesgos
- **Duplicación:** El atomic claim en JobQueue previene procesamiento doble
- **Si worker no corre:** Jobs se acumulan. No hay alerta automática de worker caído.
- **Volumen esperado:** <100 jobs/día por negocio típico

---

## 8. MÉTRICAS (cómo se calculan)

| Métrica | SQL/Fórmula | Período |
|---------|------------|---------|
| Clientes recuperados | COUNT(DISTINCT teléfonos que respondieron después de un mensaje enviado) | 30 días |
| Mensajes enviados | COUNT mensajes dirección='enviado' | 30 días |
| Tasa de conversión | (tickets finalizados / tickets totales) * 100 | 30 días |
| Tiempo ahorrado | mensajes_enviados * 3 minutos (JS frontend) | 30 días |
| Ingresos WhatsApp | SUM(costo_final) de tickets finalizados que tuvieron mensajes | 30 días |
| Último recuperado | Último mensaje recibido después de un enviado | 7 días |

**Fuente de verdad:** BD directa (queries en runtime, sin cache).

---

## 9. FRONTEND / UX

### Navegadores objetivo
- Chrome (escritorio + mobile), Safari mobile, Edge. No IE11.

### Uso esperado
- ~70% móvil (técnicos en campo), ~30% escritorio (admin)

### Componentes críticos
1. **Dashboard hero panel** — métricas de resultado (dinero + tiempo)
2. **Botón "RECUPERAR CLIENTES AHORA"** — acción de mayor valor
3. **Centro de Automatización** — 5 tabs con visibilidad total
4. **Modals de ticket** — crear, editar, ver detalle, mantenimiento
5. **Notificaciones navbar** — polling cada 30s
6. **Onboarding wizard** — 4 pasos para nuevos negocios

### Mobile
- CSS responsive via `componente_notificaciones.php`
- Barra fija inferior: 4 botones (Tickets, Auto, Diagnósticos, Config)
- Botones grandes, modals full-width

### Cache/localStorage
- `sessionStorage.setItem('ob_dismissed', 1)` — ocultar banner onboarding
- No hay otro cache. Todo se carga fresh de BD.

---

## 10. RIESGOS CONOCIDOS

### Top 5 bugs históricos (ya corregidos)
1. **Webhook sin tenant** — mensajes procesados para negocio equivocado
2. **Type string mismatch** en Diagnostico::Registrar() — queries fallaban silenciosamente
3. **BuscarCliente sin negocio_id** — leak de datos cross-tenant
4. **Jobs stuck** en "procesando" si worker crasheaba — cola se congelaba
5. **float('texto')** crasheaba el fallback de IA

### Partes que dan "miedo"
- **Tabla `persona` compartida** — no tiene negocio_id, busca por LIKE con últimos 10 dígitos
- **Conversación 24h timeout** — si pasan 24h, se crea nueva conversación (pierde contexto)
- **Sin FOREIGN KEYS** — datos huérfanos posibles si se borra un negocio
- **Rate limit file-based** — funciona pero no escala a clusters multi-servidor

### Funciona "pero no sé por qué"
- El atomic claim del JobQueue usa una variable MySQL `@claimed_job_id` — funciona en MySQL 8 pero podría no funcionar en versiones anteriores
- La normalización de teléfono asume Colombia (+57) — si se vende a otro país, se rompe

---

## 11. ESCENARIO DE USO REAL

### Usuarios esperados (primer año)
- 3-10 negocios (cada uno es un tenant)
- 1-5 usuarios por negocio (1 dueño + técnicos)
- ~10-50 tickets/mes por negocio

### Volumen de mensajes
- ~5-20 mensajes WhatsApp/día por negocio
- ~2-5 diagnósticos/semana por negocio
- ~10-30 jobs/día por negocio

### Uso típico de un negocio
```
Mañana: Dueño abre dashboard → ve métricas → click "Recuperar Clientes" → 3 mensajes enviados
Día: Técnico recibe notificación "cliente respondió" → abre ticket → registra mantenimiento
Tarde: Sistema automático evalúa scores → detecta cliente caliente → notificación al dueño
Noche: Worker procesa jobs pendientes (seguimientos 24h, scores)
```

---

## 12. DATOS DE PRUEBA

### Demo cargados (`sql/datos_demo.sql`)

**5 clientes:**
- Carlos Ramirez (573001234567) — cliente con ticket crítico, caliente
- Maria Torres (573009876543) — ticket abierto, tibia
- Andres Lopez (573005551234) — esperando repuestos, caliente
- Laura Gomez (573008884321) — sin ticket
- Pedro Martinez (573007776655) — sin ticket

**3 tickets:**
- TKT-DEMO-001: Carlos, crítico, en_sitio, en_proceso, $104,000
- TKT-DEMO-002: Maria, media, remoto, abierto, $25,000
- TKT-DEMO-003: Andres, alta, taller, esperando_repuestos, $78,000

**Conversación ejemplo (Carlos):**
```
[Bot] → "Hola Carlos! Hemos recibido el diagnóstico de tu equipo... Urgencia: CRITICA"
[Carlos] ← "cuanto vale el servicio?"
[Bot] → "El servicio en sitio tiene un costo estimado de $104,000... Agendamos para hoy o mañana?"
[Carlos] ← "si, cuando pueden venir?"
```

**3 scores:**
- Carlos: caliente ("Preguntó precio, respondió rápido")
- Maria: tibio ("No ha confirmado servicio")
- Andres: caliente ("Aceptó cotización")

### Para generar datos de test adicionales
```bash
mysql -u root -p dbsolventas17 < sql/datos_demo.sql
```
