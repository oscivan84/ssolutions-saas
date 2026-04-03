"""
Configuración del microservicio de IA.
Variables de entorno o valores por defecto.
"""
import os
from dotenv import load_dotenv

load_dotenv()

# Servidor
HOST = os.getenv("AI_SERVICE_HOST", "127.0.0.1")
PORT = int(os.getenv("AI_SERVICE_PORT", "8100"))

# Defaults (se sobreescriben por request desde PHP)
DEFAULT_PROVIDER = os.getenv("DEFAULT_AI_PROVIDER", "anthropic")
DEFAULT_MODEL = os.getenv("DEFAULT_AI_MODEL", "claude-haiku-4-5-20251001")

# Seguridad interna
INTERNAL_API_KEY = os.getenv("AI_SERVICE_INTERNAL_KEY", "ss-internal-dev-key")
