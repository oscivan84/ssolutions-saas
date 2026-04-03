"""
Fallbacks locales cuando la IA no está disponible.
Misma lógica que tenía PHP, centralizada aquí.
"""
import re


def resumen_diagnostico_local(datos: dict) -> str:
    """Genera resumen de diagnóstico sin IA."""
    cpu = datos.get("cpu", {})
    ram = datos.get("ram", {})
    disco = datos.get("disco", {})
    problemas = datos.get("problemas", [])

    resumen = "Reporte de tu equipo:\n\n"

    cpu_uso = float(cpu.get("uso_porcentaje", 0))
    if cpu_uso > 90:
        resumen += f"⚠️ Tu procesador esta trabajando al limite ({cpu_uso}%). Esto puede causar lentitud.\n"
    elif cpu_uso > 70:
        resumen += f"- Tu procesador esta algo exigido ({cpu_uso}%).\n"
    else:
        resumen += f"- Tu procesador funciona bien ({cpu_uso}% de uso).\n"

    ram_uso = float(ram.get("uso_porcentaje", 0))
    if ram_uso > 85:
        resumen += f"⚠️ La memoria RAM esta casi llena ({ram_uso}%). Tu computador necesita mas memoria o cerrar programas.\n"
    elif ram_uso > 70:
        resumen += f"- La memoria RAM esta en uso moderado ({ram_uso}%).\n"
    else:
        resumen += f"- La memoria RAM esta en buen estado ({ram_uso}%).\n"

    disco_uso = float(disco.get("uso_porcentaje", 0))
    if disco_uso > 90:
        resumen += f"⚠️ El disco duro esta casi lleno ({disco_uso}%). Necesitas liberar espacio urgentemente.\n"
    elif disco_uso > 75:
        resumen += f"- El disco tiene espacio moderado ({disco_uso}% usado).\n"
    else:
        resumen += f"- El disco tiene suficiente espacio ({disco_uso}% usado).\n"

    if problemas:
        resumen += "\nProblemas encontrados: " + ", ".join(problemas) + ".\n"

    resumen += "\nRecomendamos una revision profesional para optimizar el rendimiento de tu equipo."
    return resumen


def recomendaciones_locales(datos: dict) -> list[dict]:
    """Genera recomendaciones sin IA."""
    acciones = []
    cpu_uso = float(datos.get("cpu", {}).get("uso_porcentaje", 0))
    ram_uso = float(datos.get("ram", {}).get("uso_porcentaje", 0))
    disco_uso = float(datos.get("disco", {}).get("uso_porcentaje", 0))

    if cpu_uso > 80:
        acciones.append({
            "accion": "Optimizar procesos de inicio y desinstalar programas innecesarios",
            "prioridad": "alta" if cpu_uso > 90 else "media",
            "tiempo_estimado_min": 30,
            "requiere_repuesto": False,
        })
    if ram_uso > 85:
        acciones.append({
            "accion": "Ampliacion de memoria RAM",
            "prioridad": "alta",
            "tiempo_estimado_min": 20,
            "requiere_repuesto": True,
        })
    if disco_uso > 85:
        acciones.append({
            "accion": "Limpieza de disco y archivos temporales",
            "prioridad": "alta" if disco_uso > 95 else "media",
            "tiempo_estimado_min": 45,
            "requiere_repuesto": False,
        })
    if not acciones:
        acciones.append({
            "accion": "Mantenimiento preventivo general",
            "prioridad": "baja",
            "tiempo_estimado_min": 60,
            "requiere_repuesto": False,
        })
    return acciones


def clasificar_intencion_local(mensaje: str) -> str:
    """Clasifica intención por regex."""
    msg = mensaje.lower()
    patterns = [
        (r"\b(si|acepto|aceptar|ok|dale|confirmo|quiero|necesito)\b", "ACEPTAR_SERVICIO"),
        (r"\b(no|rechaz|cancel|despues|luego|no gracias)\b", "RECHAZAR_SERVICIO"),
        (r"\b(precio|costo|cuanto|valor|cobr|tarifa)\b", "CONSULTAR_PRECIO"),
        (r"\b(estado|como va|avance|progreso|listo)\b", "CONSULTAR_ESTADO"),
        (r"\b(agendar|cita|cuando|horario|disponib)\b", "AGENDAR_CITA"),
        (r"\b(queja|reclamo|molest|mal servicio|insatisf)\b", "QUEJA"),
        (r"\b(hola|buenos|buenas|hey|saludos)\b", "SALUDO"),
        (r"\b(gracias|adios|chao|bye|hasta luego)\b", "DESPEDIDA"),
    ]
    for pattern, intent in patterns:
        if re.search(pattern, msg):
            return intent
    return "OTRO"


def respuesta_rapida(mensaje: str, contexto: dict) -> str | None:
    """Respuestas instantáneas para mensajes simples. Retorna None si necesita IA."""
    msg = mensaje.strip().lower()
    nombre = contexto.get("nombre_cliente") or contexto.get("cliente") or ""
    empresa = contexto.get("empresa_nombre", "SSolutions")

    if re.match(r"^(hola|hey|buenas?|buenos?\s*(dias|tardes|noches)|saludos|hi|hello)[\s!.]*$", msg, re.I):
        saludo = f"Hola {nombre}!" if nombre else "Hola!"
        return (
            f"{saludo} Bienvenido a {empresa}. Como podemos ayudarte hoy?\n\n"
            "- Diagnostico de equipo\n- Estado de tu ticket\n- Cotizacion de servicio"
        )

    if re.match(r"^(gracias|thanks|ok gracias|muchas gracias|chao|bye|adios|hasta luego)[\s!.,]*$", msg, re.I):
        return "Con gusto! Si necesitas algo mas, aqui estamos. Que tengas un excelente dia!"

    return None


def respuesta_bot_local(mensaje: str, contexto: dict) -> str:
    """Respuesta completa sin IA, basada en reglas."""
    msg = mensaje.lower()

    if re.search(r"\b(si|acepto|aceptar|ok|dale|confirmo)\b", msg):
        codigo = contexto.get("codigo_ticket", "")
        suffix = f" (Ticket: {codigo})" if codigo else ""
        return f"Perfecto! Hemos registrado tu solicitud{suffix}. Un tecnico se pondra en contacto contigo pronto."

    if re.search(r"\b(no|rechaz|cancel|despues|luego)\b", msg):
        return "Entendido, no hay problema. Si cambias de opinion, no dudes en escribirnos."

    if re.search(r"\b(precio|costo|cuanto|valor|cobr)\b", msg):
        costo = contexto.get("costo_estimado", "por definir")
        try:
            costo_fmt = f"${float(costo):,.0f}"
        except (ValueError, TypeError):
            costo_fmt = "por definir"
        return f"El costo estimado del servicio es: {costo_fmt}. Quieres proceder?"

    if re.search(r"\b(estado|como va|avance|progreso)\b", msg):
        estado = contexto.get("estado", "en revision").replace("_", " ").title()
        return f"Tu ticket esta en estado: {estado}. Te notificaremos cuando haya novedades."

    if re.search(r"\b(hola|buenos|buenas|hey|saludos)\b", msg):
        nombre = contexto.get("nombre_cliente", "cliente")
        return f"Hola {nombre}! Soy el asistente de SSolutions. En que puedo ayudarte?"

    return "Gracias por tu mensaje. Un asesor revisara tu consulta y te respondera pronto."
