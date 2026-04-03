# Sistema de Soporte Tecnico - Documentacion Completa

## Arquitectura del Sistema

```
                    +------------------+
                    |  Agente Python   |
                    |  (PC del cliente)|
                    +--------+---------+
                             |
                         JSON POST
                             |
                             v
+----------+      +-------------------+      +------------------+
| WhatsApp |<---->|  Backend PHP      |<---->|  MySQL           |
| Cloud API|      |  (landingV2/)     |      |  (dbsolventas17) |
+----------+      +-------------------+      +------------------+
                             |
                         IA (API)
                             |
                    +--------+---------+
                    |  OpenAI/Anthropic|
                    +------------------+
```

## Estructura de Carpetas

```
landingV2/
├── agenteSoporte/          # Agente Python instalable
│   ├── agente.py           # Script principal
│   ├── config.json         # Configuracion del agente
│   ├── requirements.txt    # Dependencias Python
│   ├── instalar.bat        # Instalador Windows
│   └── instalar.sh         # Instalador Linux
│
├── ajax/                   # Controladores AJAX (patron SSolutions)
│   ├── TicketAjax.php      # CRUD tickets
│   ├── DiagnosticoAjax.php # Listar/ver diagnosticos
│   ├── MantenimientoAjax.php # Registrar acciones
│   ├── WhatsAppAjax.php    # Envio de mensajes
│   ├── DashboardAjax.php   # Estadisticas y logica de negocio
│   └── ConfigAjax.php      # Gestion de configuracion
│
├── api/                    # Endpoints REST
│   ├── diagnostico.php     # POST: recibir diagnostico del agente
│   ├── ticket_auto.php     # POST/GET: crear/consultar tickets
│   ├── webhook_whatsapp.php # Webhook WhatsApp (entrante/saliente)
│   └── dashboard.php       # GET: estadisticas JSON
│
├── config/                 # Configuracion
│   ├── database.php        # Conexion MySQLi + helpers
│   ├── ai.php              # Config IA (OpenAI/Anthropic)
│   └── whatsapp.php        # Config WhatsApp Cloud API
│
├── model/                  # Modelos de datos
│   ├── Diagnostico.php     # CRUD diagnosticos
│   ├── Ticket.php          # CRUD tickets + estados
│   ├── Mantenimiento.php   # Acciones con transacciones
│   └── MensajeWhatsApp.php # Log de mensajes
│
├── services/               # Capa de servicios (logica de negocio)
│   ├── AIService.php       # Resumenes, recomendaciones, bot IA
│   ├── WhatsAppService.php # Envio de mensajes WhatsApp
│   ├── TicketService.php   # Ciclo de vida del ticket
│   └── ConversacionService.php # Flujo conversacional automatico
│
├── view/                   # Vistas Bootstrap + jQuery
│   ├── soporte_dashboard.php     # Panel principal
│   ├── soporte_tickets.php       # Gestion de tickets
│   ├── soporte_diagnosticos.php  # Diagnosticos recibidos
│   └── soporte_configuracion.php # Configuracion del sistema
│
├── sql/
│   └── soporte_tables.sql  # Schema completo de BD
│
└── DOCUMENTACION.md        # Este archivo
```

---

## Flujo Completo del Sistema

### 1. Diagnostico Automatico (Agente Python)

```
python agente.py
    → Analiza CPU, RAM, Disco, Procesos, Red, Temperatura
    → Detecta problemas automaticamente
    → Ejecuta optimizaciones basicas (limpieza temporal, DNS)
    → Genera JSON completo
    → Envia POST a /api/diagnostico.php
```

### 2. Backend Procesa Diagnostico

```
POST /api/diagnostico.php
    → Valida API Key
    → Busca cliente existente (por telefono/email)
    → Calcula nivel de urgencia (algoritmo de scoring)
    → Guarda diagnostico en BD
    → Genera resumen con IA (o fallback local)
    → Crea ticket automatico
    → Envia notificacion WhatsApp al cliente
    → Retorna confirmacion con codigo de ticket
```

### 3. Flujo Conversacional WhatsApp

```
Cliente recibe mensaje: "Detectamos problemas en tu equipo..."
    → Cliente responde "SI"
    → Bot pregunta tipo de servicio (remoto/sitio/taller)
    → Cliente elige "Remoto"
    → Bot envia cotizacion
    → Cliente acepta
    → Ticket pasa a "en_diagnostico"
    → Tecnico es notificado
```

### 4. Gestion del Servicio (Panel Admin)

```
Tecnico ve ticket en panel
    → Asigna ticket a si mismo
    → Registra acciones de mantenimiento
    → Descuenta repuestos del inventario
    → Calcula costos (mano de obra + repuestos)
    → Finaliza ticket
    → Convierte a venta en sistema base
    → Cliente recibe notificacion de cierre
```

---

## Formato JSON del Agente Python

### Request: POST /api/diagnostico.php

```json
{
    "cliente": {
        "nombre": "Juan Perez",
        "telefono": "3001234567",
        "email": "juan@email.com"
    },
    "sistema": {
        "hostname": "PC-JUAN",
        "os": "Windows 10",
        "os_version": "10.0.19045",
        "arquitectura": "AMD64",
        "usuario": "juan",
        "boot_time": "2026-04-03T08:00:00"
    },
    "cpu": {
        "modelo": "Intel Core i5-10400",
        "uso_porcentaje": 78.5,
        "nucleos": 12,
        "nucleos_fisicos": 6,
        "frecuencia_mhz": 2900,
        "frecuencia_max_mhz": 4300
    },
    "ram": {
        "total_gb": 16.0,
        "usada_gb": 12.8,
        "disponible_gb": 3.2,
        "uso_porcentaje": 80.0
    },
    "disco": {
        "total_gb": 476.94,
        "usado_gb": 380.55,
        "libre_gb": 96.39,
        "uso_porcentaje": 79.8
    },
    "temperatura_cpu": 65.0,
    "procesos_activos": 185,
    "procesos_alto_consumo": [
        {"nombre": "chrome.exe", "cpu": 25.3, "ram": 8.2}
    ],
    "red": {
        "conectado": true,
        "bytes_enviados_mb": 1250.3,
        "bytes_recibidos_mb": 5840.7,
        "hostname": "PC-JUAN"
    },
    "problemas": [
        "Memoria RAM elevada: 80.0% en uso",
        "Procesos con alto consumo: chrome.exe"
    ],
    "recomendaciones": [
        "Cerrar pestanas del navegador y aplicaciones no necesarias",
        "Revisar si estos programas son necesarios: chrome.exe"
    ],
    "optimizaciones": [
        "Archivos temporales eliminados: 23",
        "Cache de DNS limpiado"
    ],
    "agente_version": "1.0.0",
    "fecha": "2026-04-03T14:30:00"
}
```

### Response: Exito

```json
{
    "status": "success",
    "message": "Diagnostico recibido y procesado",
    "data": {
        "iddiagnostico": 15,
        "ticket": {
            "success": true,
            "idticket": 22,
            "codigo": "TKT-20260403-A1B2C"
        },
        "nivel_urgencia": "media",
        "resumen_ia": "Tu equipo esta funcionando bien en general, pero la memoria RAM esta algo exigida (80%). Recomendamos cerrar pestanas del navegador que no estes usando.",
        "whatsapp_enviado": true
    }
}
```

---

## Estados del Ticket

```
abierto → en_diagnostico → en_proceso → finalizado
    ↓          ↓               ↓
    ↓          ↓         esperando_repuestos → en_proceso
    ↓          ↓
    └──────────└──────────→ cancelado

cancelado → abierto (reabrir)
```

### Transiciones permitidas:
| Estado actual | Puede ir a |
|---|---|
| abierto | en_diagnostico, en_proceso, cancelado |
| en_diagnostico | en_proceso, esperando_repuestos, cancelado |
| en_proceso | esperando_repuestos, finalizado, cancelado |
| esperando_repuestos | en_proceso, cancelado |
| finalizado | (sin transicion) |
| cancelado | abierto |

---

## Tipos de Servicio

| Tipo | Descripcion | Tarifa/hora (por defecto) |
|---|---|---|
| remoto | Asistencia a distancia (TeamViewer, AnyDesk) | $25,000 |
| en_sitio | Tecnico va a la ubicacion del cliente | $40,000 |
| taller | Cliente lleva equipo al taller | $30,000 |

---

## Algoritmo de Urgencia

```
score = 0

CPU > 90%    → +3 puntos
CPU > 75%    → +1 punto

RAM > 90%    → +3 puntos
RAM > 80%    → +1 punto

Disco > 95%  → +3 puntos
Disco > 85%  → +1 punto

score += cantidad_de_problemas

score >= 8 → CRITICA
score >= 5 → ALTA
score >= 2 → MEDIA
score < 2  → BAJA
```

---

## Instalacion

### 1. Base de datos
```sql
-- Ejecutar en MySQL:
source landingV2/sql/soporte_tables.sql
```

### 2. Configuracion
- Copiar `.env.example` a `.env` y configurar credenciales de BD
- Acceder al panel de configuracion y configurar:
  - WhatsApp tokens (si aplica)
  - API key de IA (si aplica)
  - Tarifas de servicio
  - API key del agente Python

### 3. Agente Python (en PC del cliente)
```bash
# Windows
cd agenteSoporte
instalar.bat

# Linux
cd agenteSoporte
chmod +x instalar.sh
./instalar.sh
```

### 4. WhatsApp Webhook
- URL: `https://tu-dominio.com/landingV2/api/webhook_whatsapp.php`
- Configurar en Meta Business Suite > WhatsApp > Configuration > Webhook

---

## Tablas de Base de Datos

| Tabla | Proposito |
|---|---|
| diagnosticos | Reportes del agente Python |
| tickets | Tickets de soporte |
| historial_estados_ticket | Auditoria de cambios de estado |
| mantenimientos | Acciones realizadas por tecnicos |
| mensajes_whatsapp | Log de mensajes WhatsApp |
| conversaciones_whatsapp | Flujos conversacionales activos |
| plantillas_mensaje | Plantillas reutilizables |
| configuracion_soporte | Key-value de configuracion |

**Tablas del sistema base reutilizadas:**
| Tabla | Uso |
|---|---|
| persona | Clientes (idpersona) |
| articulo | Repuestos/inventario (idarticulo, stock_actual) |
| usuario | Tecnicos (idusuario) |
| venta | Conversion ticket → venta (idventa) |

---

## API Endpoints

| Metodo | Endpoint | Descripcion | Auth |
|---|---|---|---|
| POST | /api/diagnostico.php | Recibir diagnostico del agente | X-API-Key |
| POST | /api/ticket_auto.php | Crear ticket | - |
| GET | /api/ticket_auto.php?codigo=X | Consultar ticket | - |
| GET | /api/dashboard.php | Estadisticas generales | - |
| GET/POST | /api/webhook_whatsapp.php | Webhook WhatsApp | verify_token |

---

## Capa de IA

### Con API configurada (OpenAI/Anthropic):
- Resumenes de diagnostico en lenguaje humano
- Recomendaciones de servicio estructuradas (JSON)
- Respuestas inteligentes para el bot de WhatsApp
- Clasificacion de intenciones del cliente

### Sin API (modo local):
- Resumenes basados en reglas (umbrales CPU/RAM/Disco)
- Recomendaciones predefinidas segun metricas
- Respuestas del bot por reconocimiento de patrones (regex)
- Clasificacion de intenciones por palabras clave

El sistema funciona completamente en ambos modos.
