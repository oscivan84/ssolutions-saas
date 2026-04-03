# DEPLOY GUIDE — SSolutions SaaS

Deploy completo en plataformas gratuitas. Costo: $0. Tiempo: ~1 hora.

## Arquitectura Final

```
┌─────────────────────────┐     ┌─────────────────────────┐
│  Railway (PHP + Apache) │     │   Render (FastAPI)       │
│  Backend + Webhook WA   │────>│   AI Microservice        │
│  $0/mes (free tier)     │     │   $0/mes (free tier)     │
└───────────┬─────────────┘     └─────────────────────────┘
            │
            v
┌─────────────────────────┐     ┌─────────────────────────┐
│  Railway MySQL           │     │   cron-job.org           │
│  Base de datos           │     │   Worker cada 1 min      │
│  $0/mes (free tier)     │     │   $0/mes (free)          │
└─────────────────────────┘     └─────────────────────────┘
```

---

## PASO 1: Deploy Microservicio IA (Render) — 10 min

### 1.1 Crear cuenta en Render
1. Ir a https://render.com
2. Sign up con GitHub

### 1.2 Crear Web Service
1. Dashboard → New → Web Service
2. Conectar repo: `oscivan84/ssolutions-saas`
3. Configurar:
   - **Name:** `ssolutions-ai`
   - **Root Directory:** `ai-service`
   - **Runtime:** Python 3
   - **Build Command:** `pip install -r requirements.txt`
   - **Start Command:** `uvicorn api:app --host 0.0.0.0 --port $PORT`
   - **Instance Type:** Free

### 1.3 Variables de entorno (Render)
```
AI_SERVICE_INTERNAL_KEY = ss-prod-key-cambiar-esto
DEFAULT_AI_PROVIDER = anthropic
DEFAULT_AI_MODEL = claude-haiku-4-5-20251001
```

### 1.4 Verificar
```bash
curl https://ssolutions-ai.onrender.com/health
# Debe retornar: {"status":"ok","service":"ai-service","version":"1.0.0"}
```

**Nota:** Free tier de Render duerme después de 15 min sin tráfico. Primer request toma ~30s (cold start).

---

## PASO 2: Deploy Base de Datos (Railway MySQL) — 10 min

### 2.1 Crear cuenta en Railway
1. Ir a https://railway.app
2. Sign up con GitHub

### 2.2 Crear MySQL
1. Dashboard → New Project → Provision MySQL
2. Railway genera automáticamente: host, port, user, password, database

### 2.3 Obtener credenciales
En Railway → MySQL service → Variables:
```
MYSQL_HOST = containers-us-west-xxx.railway.app
MYSQL_PORT = 7890
MYSQL_USER = root
MYSQL_PASSWORD = (generado)
MYSQL_DATABASE = railway
```

### 2.4 Importar schema
Desde tu terminal local con MySQL client:
```bash
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/soporte_tables.sql
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/migration_advanced.sql
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/migration_ux.sql
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/migration_modular.sql
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/migration_versionado.sql
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/migration_funnel.sql
mysql -h MYSQL_HOST -P MYSQL_PORT -u root -pMYSQL_PASSWORD railway < sql/datos_demo.sql
```

---

## PASO 3: Deploy Backend PHP (Railway) — 15 min

### 3.1 Crear servicio PHP
1. Railway Dashboard → New Service → GitHub Repo → `oscivan84/ssolutions-saas`
2. Railway detecta el Dockerfile automáticamente

### 3.2 Variables de entorno (Railway PHP)
```
DB_HOST = (del paso 2: MYSQL_HOST)
DB_USER = root
DB_PASS = (del paso 2: MYSQL_PASSWORD)
DB_NAME = railway
AI_SERVICE_URL = https://ssolutions-ai.onrender.com
AI_SERVICE_INTERNAL_KEY = ss-prod-key-cambiar-esto
MASTER_ENCRYPT_KEY = (generar: openssl rand -hex 16)
APP_ENV = production
CRON_SECRET_KEY = (generar: openssl rand -hex 16)
PORT = 80
```

### 3.3 Verificar
```bash
# Dashboard
curl https://tu-app.up.railway.app/landingV2/api/dashboard.php
# Debe retornar JSON con stats

# Webhook verificación
curl https://tu-app.up.railway.app/landingV2/api/webhook_whatsapp.php
# Debe retornar 405 (method not allowed) — correcto
```

---

## PASO 4: Configurar Worker (cron-job.org) — 5 min

### 4.1 Crear cuenta
1. Ir a https://cron-job.org
2. Registrarse gratis

### 4.2 Crear cron job
- **URL:** `https://tu-app.up.railway.app/landingV2/api/worker_http.php?key=TU_CRON_SECRET_KEY`
- **Ejecución:** Cada 1 minuto
- **Método:** GET
- **Timeout:** 60 segundos

---

## PASO 5: Configurar WhatsApp (Meta) — 20 min

### 5.1 Crear app en Meta
1. Ir a https://developers.facebook.com
2. My Apps → Create App → Business → WhatsApp
3. En WhatsApp → Getting Started:
   - Obtener **Temporary Access Token**
   - Obtener **Phone Number ID**

### 5.2 Configurar Webhook
1. WhatsApp → Configuration → Webhook
2. **Callback URL:** `https://tu-app.up.railway.app/landingV2/api/webhook_whatsapp.php`
3. **Verify Token:** (elegir uno, ej: `mi-verify-token-2026`)
4. Subscribir a: `messages`

### 5.3 Configurar en el sistema
Desde el panel admin de SSolutions (Configuración):
- **WhatsApp Token:** (el temporary access token)
- **Phone Number ID:** (el ID del número)
- **Verify Token:** `mi-verify-token-2026`
- **AI Provider:** anthropic
- **AI API Key:** (tu key de Anthropic)
- **AI Model:** claude-haiku-4-5-20251001

---

## PASO 6: Validación End-to-End — 10 min

### Checklist

```
[ ] 1. AI Service responde:
      curl https://ssolutions-ai.onrender.com/health → {"status":"ok"}

[ ] 2. Dashboard funciona:
      curl https://tu-app.up.railway.app/landingV2/api/dashboard.php → JSON con datos

[ ] 3. Webhook registrado en Meta:
      Meta muestra "Webhook verified" ✓

[ ] 4. Worker ejecuta:
      curl https://tu-app.up.railway.app/landingV2/api/worker_http.php?key=TU_KEY → {"status":"ok"}

[ ] 5. Enviar mensaje de prueba:
      Desde tu WhatsApp personal → enviar "Hola" al número Business
      → Bot debe responder con opciones

[ ] 6. Verificar en BD:
      SELECT * FROM mensajes_whatsapp ORDER BY fecha DESC LIMIT 5;
      → Debe mostrar mensaje enviado + recibido

[ ] 7. Verificar funnel:
      SELECT * FROM funnel_conversiones ORDER BY fecha_contacto DESC LIMIT 5;
      → Debe tener registro del contacto
```

---

## Variables de Entorno Completas

### Railway (PHP Backend)
| Variable | Valor | Descripción |
|----------|-------|-------------|
| `DB_HOST` | (de Railway MySQL) | Host de la BD |
| `DB_USER` | `root` | Usuario BD |
| `DB_PASS` | (de Railway MySQL) | Password BD |
| `DB_NAME` | `railway` | Nombre BD |
| `AI_SERVICE_URL` | `https://ssolutions-ai.onrender.com` | URL del microservicio IA |
| `AI_SERVICE_INTERNAL_KEY` | (generar) | Clave interna IA |
| `MASTER_ENCRYPT_KEY` | (generar) | Clave encriptación |
| `APP_ENV` | `production` | Entorno |
| `CRON_SECRET_KEY` | (generar) | Clave del worker HTTP |

### Render (FastAPI)
| Variable | Valor |
|----------|-------|
| `AI_SERVICE_INTERNAL_KEY` | (misma que Railway) |

---

## Riesgos y Mitigaciones (Free Tier)

| Riesgo | Impacto | Mitigación |
|--------|---------|-----------|
| **Cold start Render** (30s) | Primera respuesta lenta | El fallback local PHP responde mientras IA arranca |
| **Railway free tier** (500h/mes) | Se apaga si excedes | Suficiente para pruebas (~16h/día) |
| **MySQL Railway** (1GB) | Límite de datos | Limpiar jobs viejos (worker ya lo hace) |
| **Cron-job.org** (1 min mínimo) | Jobs esperan hasta 1 min | Aceptable para MVP |
| **HTTPS automático** | Ambas plataformas lo proveen | Sin acción necesaria |

---

## Costos

| Servicio | Plan | Costo |
|----------|------|-------|
| Railway (PHP) | Free | $0 |
| Railway (MySQL) | Free | $0 |
| Render (FastAPI) | Free | $0 |
| cron-job.org | Free | $0 |
| Meta WhatsApp | Free (1000 msgs/mes) | $0 |
| **TOTAL** | | **$0/mes** |

---

## Comandos de Deploy

```bash
# Desde tu máquina local:
git push origin master
# → Railway auto-deploya PHP
# → Render auto-deploya FastAPI (si configurado)

# Para forzar redeploy:
# Railway: Dashboard → Redeploy
# Render: Dashboard → Manual Deploy
```
