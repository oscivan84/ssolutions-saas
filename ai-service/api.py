"""
SSolutions AI Microservice
===========================
Motor de IA centralizado. PHP llama aquí en vez de llamar directamente a Anthropic/OpenAI.

Endpoints:
  POST /responder         — Bot WhatsApp orientado a ventas
  POST /diagnostico       — Resumen de diagnóstico en lenguaje humano
  POST /recomendaciones   — Acciones técnicas recomendadas (JSON)
  POST /clasificar        — Clasificar intención del mensaje
  POST /score-cliente     — Scoring: caliente/tibio/frio
  POST /sugerencia-tecnico — Sugerir al técnico qué hacer
  GET  /health            — Health check

Ejecutar: uvicorn api:app --host 127.0.0.1 --port 8100
"""

import json
import time
from collections import defaultdict
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel
import config
from logic.providers import call_provider
from logic.fallbacks import (
    respuesta_rapida,
    respuesta_bot_local,
    resumen_diagnostico_local,
    recomendaciones_locales,
    clasificar_intencion_local,
)
from prompts.templates import (
    ventas,
    diagnostico as prompt_diagnostico,
    recomendaciones as prompt_recomendaciones,
    clasificar_intencion as prompt_clasificar,
    score_cliente as prompt_score,
    sugerencia_tecnico as prompt_sugerencia,
)

app = FastAPI(title="SSolutions AI Service", version="1.0.0")

# ============================================================
# RATE LIMITING (por API key = por negocio)
# ============================================================
_rate_limits: dict[str, list[float]] = defaultdict(list)
RATE_LIMIT_MAX = 60       # requests
RATE_LIMIT_WINDOW = 60    # segundos

def check_rate_limit(api_key: str):
    """Limita requests por negocio (via api_key). 60 req/min por defecto."""
    if not api_key:
        return
    now = time.time()
    # Limpiar entradas viejas
    _rate_limits[api_key] = [t for t in _rate_limits[api_key] if now - t < RATE_LIMIT_WINDOW]
    if len(_rate_limits[api_key]) >= RATE_LIMIT_MAX:
        raise HTTPException(status_code=429, detail="Rate limit exceeded")
    _rate_limits[api_key].append(now)
    # Limpiar keys vacías para evitar memory leak
    dead_keys = [k for k, v in _rate_limits.items() if not v]
    for k in dead_keys:
        del _rate_limits[k]


# ============================================================
# MODELOS DE REQUEST/RESPONSE
# ============================================================

class AIConfig(BaseModel):
    """Configuración de IA pasada desde PHP (por negocio)."""
    provider: str = "anthropic"
    api_key: str = ""
    model: str = "claude-haiku-4-5-20251001"


class NegocioConfig(BaseModel):
    """Datos del negocio para personalizar prompts."""
    empresa_nombre: str = "SSolutions"
    moneda_simbolo: str = "$"
    costo_hora_remoto: str = "25000"
    costo_hora_sitio: str = "40000"
    costo_hora_taller: str = "30000"


class ResponderRequest(BaseModel):
    mensaje: str
    contexto: dict = {}
    negocio: NegocioConfig = NegocioConfig()
    ai: AIConfig = AIConfig()
    system_prompt_override: str | None = None


class DiagnosticoRequest(BaseModel):
    datos: dict
    negocio: NegocioConfig = NegocioConfig()
    ai: AIConfig = AIConfig()


class RecomendacionesRequest(BaseModel):
    datos: dict
    tipo_servicio: str = "remoto"
    negocio: NegocioConfig = NegocioConfig()
    ai: AIConfig = AIConfig()


class ClasificarRequest(BaseModel):
    mensaje: str
    ai: AIConfig = AIConfig()


class ScoreRequest(BaseModel):
    contexto: dict = {}
    historial: str = ""
    ai: AIConfig = AIConfig()


class SugerenciaRequest(BaseModel):
    ticket: dict = {}
    diagnostico_data: dict = {}
    ai: AIConfig = AIConfig()


class AIResponse(BaseModel):
    respuesta: str | None = None
    source: str = "ia"  # "ia", "fallback", "rapida"
    error: str | None = None


# ============================================================
# MIDDLEWARE: Auth interna
# ============================================================

def verify_internal_key(x_internal_key: str = Header(default="")):
    if config.INTERNAL_API_KEY and x_internal_key != config.INTERNAL_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid internal API key")


# ============================================================
# ENDPOINTS
# ============================================================

@app.get("/health")
async def health():
    return {"status": "ok", "service": "ai-service", "version": "1.0.0"}


@app.post("/responder", response_model=AIResponse)
async def responder_whatsapp(req: ResponderRequest, x_internal_key: str = Header(default="")):
    """Bot WhatsApp orientado a ventas. Triple fallback: rápida → local → IA."""
    verify_internal_key(x_internal_key)

    # 1. Respuesta rápida (sin IA)
    ctx = {**req.contexto, "empresa_nombre": req.negocio.empresa_nombre}
    rapida = respuesta_rapida(req.mensaje, ctx)
    if rapida:
        return AIResponse(respuesta=rapida, source="rapida")

    # 2. Si no hay API key → fallback local
    if not req.ai.api_key:
        local = respuesta_bot_local(req.mensaje, req.contexto)
        return AIResponse(respuesta=local, source="fallback")

    # 3. IA con prompt de ventas (soporta override para optimizador)
    check_rate_limit(req.ai.api_key)
    if req.system_prompt_override:
        system_prompt = req.system_prompt_override
        user_prompt = req.mensaje
    else:
        system_prompt, user_prompt = ventas(req.negocio.model_dump(), req.contexto, req.mensaje)
    resultado = await call_provider(
        req.ai.provider, req.ai.api_key, req.ai.model,
        system_prompt, user_prompt
    )

    if resultado:
        return AIResponse(respuesta=resultado, source="ia")

    # Fallback si IA falla
    local = respuesta_bot_local(req.mensaje, req.contexto)
    return AIResponse(respuesta=local, source="fallback", error="IA no disponible")


@app.post("/diagnostico", response_model=AIResponse)
async def resumir_diagnostico(req: DiagnosticoRequest, x_internal_key: str = Header(default="")):
    """Resumen de diagnóstico de PC en lenguaje humano."""
    verify_internal_key(x_internal_key)

    if not req.ai.api_key:
        local = resumen_diagnostico_local(req.datos)
        return AIResponse(respuesta=local, source="fallback")

    system_prompt, user_prompt = prompt_diagnostico(req.negocio.model_dump(), req.datos)
    resultado = await call_provider(
        req.ai.provider, req.ai.api_key, req.ai.model,
        system_prompt, user_prompt
    )

    if resultado:
        return AIResponse(respuesta=resultado, source="ia")

    local = resumen_diagnostico_local(req.datos)
    return AIResponse(respuesta=local, source="fallback", error="IA no disponible")


@app.post("/recomendaciones")
async def generar_recomendaciones(req: RecomendacionesRequest, x_internal_key: str = Header(default="")):
    """Genera recomendaciones técnicas en formato JSON."""
    verify_internal_key(x_internal_key)

    if not req.ai.api_key:
        return {"recomendaciones": recomendaciones_locales(req.datos), "source": "fallback"}

    system_prompt, user_prompt = prompt_recomendaciones(
        req.negocio.model_dump(), req.datos, req.tipo_servicio
    )
    resultado = await call_provider(
        req.ai.provider, req.ai.api_key, req.ai.model,
        system_prompt, user_prompt
    )

    if resultado:
        # Intentar parsear JSON
        try:
            parsed = json.loads(resultado)
            return {"recomendaciones": parsed, "source": "ia"}
        except json.JSONDecodeError:
            import re
            match = re.search(r"\[.*\]", resultado, re.DOTALL)
            if match:
                try:
                    parsed = json.loads(match.group())
                    return {"recomendaciones": parsed, "source": "ia"}
                except json.JSONDecodeError:
                    pass

    return {"recomendaciones": recomendaciones_locales(req.datos), "source": "fallback"}


@app.post("/clasificar")
async def clasificar_intencion(req: ClasificarRequest, x_internal_key: str = Header(default="")):
    """Clasifica la intención del mensaje del cliente."""
    verify_internal_key(x_internal_key)

    if not req.ai.api_key:
        return {"intencion": clasificar_intencion_local(req.mensaje), "source": "fallback"}

    system_prompt, _ = prompt_clasificar()
    resultado = await call_provider(
        req.ai.provider, req.ai.api_key, req.ai.model,
        system_prompt, req.mensaje, max_tokens=20, temperature=0.1
    )

    if resultado:
        clean = resultado.strip().upper()
        validas = [
            "ACEPTAR_SERVICIO", "RECHAZAR_SERVICIO", "CONSULTAR_PRECIO",
            "CONSULTAR_ESTADO", "AGENDAR_CITA", "QUEJA", "SALUDO", "DESPEDIDA", "OTRO"
        ]
        if clean in validas:
            return {"intencion": clean, "source": "ia"}

    return {"intencion": clasificar_intencion_local(req.mensaje), "source": "fallback"}


@app.post("/score-cliente")
async def score_cliente(req: ScoreRequest, x_internal_key: str = Header(default="")):
    """Scoring de cliente: caliente/tibio/frio."""
    verify_internal_key(x_internal_key)

    if not req.ai.api_key:
        return {"score": "tibio", "razon": "Sin IA configurada", "accion_sugerida": "Seguimiento manual", "source": "fallback"}

    system_prompt, user_prompt = prompt_score(req.contexto, req.historial)
    resultado = await call_provider(
        req.ai.provider, req.ai.api_key, req.ai.model,
        system_prompt, user_prompt, max_tokens=150, temperature=0.3
    )

    if resultado:
        try:
            parsed = json.loads(resultado)
            parsed["source"] = "ia"
            return parsed
        except json.JSONDecodeError:
            pass

    return {"score": "tibio", "razon": "No se pudo evaluar", "accion_sugerida": "Revisar manualmente", "source": "fallback"}


@app.post("/sugerencia-tecnico")
async def sugerencia_tecnico(req: SugerenciaRequest, x_internal_key: str = Header(default="")):
    """Sugiere al técnico qué hacer/decir cuando abre un ticket."""
    verify_internal_key(x_internal_key)

    if not req.ai.api_key:
        return {"mensaje_sugerido": "Contactar al cliente para confirmar el servicio", "acciones": [], "upsell": [], "source": "fallback"}

    system_prompt, user_prompt = prompt_sugerencia(req.ticket, req.diagnostico_data)
    resultado = await call_provider(
        req.ai.provider, req.ai.api_key, req.ai.model,
        system_prompt, user_prompt, max_tokens=300
    )

    if resultado:
        try:
            parsed = json.loads(resultado)
            parsed["source"] = "ia"
            return parsed
        except json.JSONDecodeError:
            pass

    return {"mensaje_sugerido": "Contactar al cliente", "acciones": ["Revisar diagnostico"], "upsell": [], "source": "fallback"}


# ============================================================
# ENTRY POINT
# ============================================================

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("api:app", host=config.HOST, port=config.PORT, reload=True)
