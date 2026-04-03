"""
Plantillas de prompts centralizadas.
Cada función retorna (system_prompt, user_prompt).
Las variables se inyectan desde el contexto del negocio.
"""


def _sanitize(value: str, max_len: int = 100) -> str:
    """Sanitiza valores inyectados en prompts para prevenir prompt injection."""
    if not isinstance(value, str):
        value = str(value)
    # Remover caracteres de control y limitar largo
    value = value.replace("\n", " ").replace("\r", "")
    return value[:max_len]


def ventas(negocio: dict, contexto: dict, mensaje: str) -> tuple[str, str]:
    """Prompt orientado a cierre de venta por WhatsApp."""
    empresa = _sanitize(negocio.get("empresa_nombre", "SSolutions"), 50)
    moneda = _sanitize(negocio.get("moneda_simbolo", "$"), 5)
    remoto = _sanitize(negocio.get("costo_hora_remoto", "25000"), 10)
    sitio = _sanitize(negocio.get("costo_hora_sitio", "40000"), 10)
    taller = _sanitize(negocio.get("costo_hora_taller", "30000"), 10)

    system = f"""Eres un asistente de ventas y soporte para {empresa}, un taller de reparacion y soporte tecnico.

OBJETIVO PRINCIPAL:
Convertir cada conversacion en una venta o cita de servicio.

REGLAS:
- Respuestas cortas (maximo 80 palabras)
- Amigable pero profesional
- Siempre intentar avanzar la conversacion hacia el cierre
- No dar demasiada informacion tecnica innecesaria
- Guiar hacia: diagnostico → cotizacion → confirmacion
- Si el cliente duda, ofrecer diagnostico gratuito o descuento
- Usar urgencia cuando sea apropiado (si hay problemas criticos)
- Moneda: {moneda}

SERVICIOS DISPONIBLES:
- Remoto: {moneda}{remoto}/hora
- En sitio: {moneda}{sitio}/hora
- Taller: {moneda}{taller}/hora

TECNICAS DE CIERRE:
- Preguntas de cierre: 'Agendamos para hoy o manana?', 'Prefieres remoto o en sitio?'
- Crear urgencia: 'Este tipo de problema puede empeorar si no se atiende'
- Ofrecer valor: 'El diagnostico incluye optimizacion basica sin costo adicional'"""

    user = _build_context_block(contexto)
    user += f"\nMENSAJE DEL CLIENTE:\n{mensaje}\n\nGenera una respuesta orientada a cerrar la venta o avanzar en el proceso."

    return system, user


def diagnostico(negocio: dict, datos: dict) -> tuple[str, str]:
    """Prompt para resumir diagn��stico de PC en lenguaje sencillo."""
    system = (
        "Eres un tecnico de soporte amigable. Explica los problemas del computador "
        "en lenguaje sencillo que cualquier persona pueda entender. "
        "Se breve (maximo 150 palabras). Usa un tono profesional pero cercano. "
        "Incluye recomendaciones practicas."
    )

    cpu = datos.get("cpu", {})
    ram = datos.get("ram", {})
    disco = datos.get("disco", {})
    problemas = datos.get("problemas", [])

    user = "Diagnostico de PC:\n"
    user += f"- CPU: {cpu.get('modelo', 'N/A')} al {cpu.get('uso_porcentaje', '?')}% de uso\n"
    user += f"- RAM: {ram.get('usada_gb', '?')}GB de {ram.get('total_gb', '?')}GB ({ram.get('uso_porcentaje', '?')}%)\n"
    user += f"- Disco: {disco.get('usado_gb', '?')}GB de {disco.get('total_gb', '?')}GB ({disco.get('uso_porcentaje', '?')}%)\n"

    if problemas:
        user += "\nProblemas detectados:\n"
        for p in problemas:
            user += f"- {p}\n"

    user += "\nExplica esto en lenguaje sencillo y da recomendaciones."
    return system, user


def recomendaciones(negocio: dict, datos: dict, tipo_servicio: str = "remoto") -> tuple[str, str]:
    """Prompt para generar recomendaciones técnicas en JSON."""
    import json

    system = "Eres un tecnico experto en soporte. Responde SOLO con JSON valido, sin texto adicional."

    user = (
        f"Basado en este diagnostico de PC:\n{json.dumps(datos, indent=2, ensure_ascii=False)}\n\n"
        f"Tipo de servicio solicitado: {tipo_servicio}\n"
        "Genera una lista de acciones recomendadas con estimacion de tiempo y prioridad. "
        'Formato JSON: [{"accion": "...", "prioridad": "alta|media|baja", "tiempo_estimado_min": N, "requiere_repuesto": true/false}]'
    )
    return system, user


def clasificar_intencion() -> tuple[str, str]:
    """System prompt para clasificación de intención (user prompt es el mensaje)."""
    system = (
        "Clasifica la intencion del siguiente mensaje de un cliente de soporte tecnico. "
        "Responde SOLO con una de estas categorias: "
        "ACEPTAR_SERVICIO, RECHAZAR_SERVICIO, CONSULTAR_PRECIO, CONSULTAR_ESTADO, "
        "AGENDAR_CITA, QUEJA, SALUDO, DESPEDIDA, OTRO"
    )
    return system, ""


def score_cliente(contexto: dict, historial: str) -> tuple[str, str]:
    """Prompt para scoring de cliente (caliente/tibio/frio)."""
    system = """Eres un analista de ventas. Evalua al cliente y responde SOLO con JSON:
{"score": "caliente|tibio|frio", "razon": "breve explicacion", "accion_sugerida": "que hacer"}

Criterios:
- caliente: quiere comprar, pregunta precios, acepta citas, tiene urgencia
- tibio: interesado pero indeciso, pide mas info, dice "despues"
- frio: solo saluda, no responde, rechaza, se queja"""

    user = _build_context_block(contexto)
    if historial:
        user += f"\nHISTORIAL DE MENSAJES:\n{historial}\n"
    user += "\nEvalua este cliente."
    return system, user


def sugerencia_tecnico(ticket: dict, diagnostico_data: dict) -> tuple[str, str]:
    """Prompt para sugerir al técnico qué decir/hacer."""
    import json

    system = """Eres un asesor para tecnicos de soporte. Sugiere:
1. Que decirle al cliente (mensaje corto)
2. Acciones tecnicas recomendadas (lista)
3. Oportunidades de venta adicional (upsell)
Responde en JSON: {"mensaje_sugerido": "...", "acciones": ["..."], "upsell": ["..."]}"""

    user = f"TICKET: {json.dumps(ticket, ensure_ascii=False)}\n"
    if diagnostico_data:
        user += f"DIAGNOSTICO: {json.dumps(diagnostico_data, ensure_ascii=False)}\n"
    return system, user


def _build_context_block(contexto: dict) -> str:
    """Helper: construye bloque de contexto del cliente."""
    if not contexto:
        return ""

    block = "CONTEXTO DEL CLIENTE:\n"
    mappings = [
        (["nombre_cliente", "cliente"], "Nombre"),
        (["codigo_ticket"], "Ticket"),
        (["estado"], "Estado actual"),
        (["tipo_servicio"], "Tipo servicio"),
        (["costo_estimado"], "Costo estimado"),
        (["resumen_ia", "descripcion"], "Problema"),
    ]
    for keys, label in mappings:
        for k in keys:
            v = contexto.get(k)
            if v and str(v).strip():
                block += f"- {label}: {v}\n"
                break

    historial = contexto.get("historial_mensajes", "")
    if historial:
        block += f"\nHISTORIAL RECIENTE:\n{historial}\n"

    return block + "\n"
