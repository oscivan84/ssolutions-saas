"""
Fixtures compartidas para tests de la API de IA.
"""
import pytest
from fastapi.testclient import TestClient
import sys
import os

# Agregar ai-service al path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', 'ai-service'))

from api import app


@pytest.fixture
def client():
    """Cliente HTTP para testing."""
    return TestClient(app)


@pytest.fixture
def auth_header():
    """Header de autenticación interna."""
    return {"X-Internal-Key": "ss-internal-dev-key"}


@pytest.fixture
def ai_config_con_key():
    """Config de IA con API key (simula negocio con plan Pro+)."""
    return {
        "provider": "anthropic",
        "api_key": "test-key-fake",
        "model": "claude-haiku-4-5-20251001"
    }


@pytest.fixture
def ai_config_sin_key():
    """Config de IA sin API key (simula plan Básico / IA no configurada)."""
    return {
        "provider": "anthropic",
        "api_key": "",
        "model": ""
    }


@pytest.fixture
def negocio_config():
    """Config típica de un negocio."""
    return {
        "empresa_nombre": "TechRepair Test",
        "moneda_simbolo": "$",
        "costo_hora_remoto": "25000",
        "costo_hora_sitio": "40000",
        "costo_hora_taller": "30000"
    }


@pytest.fixture
def contexto_cliente():
    """Contexto típico de un cliente con ticket."""
    return {
        "nombre_cliente": "Carlos Test",
        "codigo_ticket": "TKT-TEST-001",
        "estado": "abierto",
        "tipo_servicio": "remoto",
        "costo_estimado": "50000",
        "resumen_ia": "CPU al 92%, RAM al 90%. Equipo necesita optimización urgente."
    }


@pytest.fixture
def datos_diagnostico():
    """Datos de diagnóstico típicos."""
    return {
        "cpu": {"modelo": "Intel i5-10400", "uso_porcentaje": 92, "nucleos": 6},
        "ram": {"total_gb": 8, "usada_gb": 7.2, "uso_porcentaje": 90},
        "disco": {"total_gb": 500, "usado_gb": 475, "uso_porcentaje": 95},
        "problemas": ["CPU al 92%", "RAM casi llena", "Disco al 95%"]
    }
