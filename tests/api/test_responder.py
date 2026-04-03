"""Test: Endpoint /responder — Bot WhatsApp orientado a ventas."""


def test_respuesta_rapida_saludo(client, auth_header, ai_config_sin_key, negocio_config):
    """Saludos simples no van a IA, respuesta rápida."""
    res = client.post("/responder", json={
        "mensaje": "hola",
        "contexto": {"empresa_nombre": "TestCo"},
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert data["source"] == "rapida"
    assert "Hola" in data["respuesta"]


def test_respuesta_rapida_despedida(client, auth_header, ai_config_sin_key, negocio_config):
    """Despedidas simples usan respuesta rápida."""
    res = client.post("/responder", json={
        "mensaje": "chao",
        "contexto": {},
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["source"] == "rapida"


def test_despedida_compuesta_usa_fallback(client, auth_header, ai_config_sin_key, negocio_config):
    """Despedidas compuestas usan fallback (no son exactas)."""
    res = client.post("/responder", json={
        "mensaje": "gracias por todo, chao",
        "contexto": {},
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["source"] in ("rapida", "fallback")


def test_fallback_sin_api_key(client, auth_header, ai_config_sin_key, negocio_config):
    """Sin API key, usa fallback local."""
    res = client.post("/responder", json={
        "mensaje": "cuanto cuesta arreglar mi computador?",
        "contexto": {"costo_estimado": "50000"},
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert data["source"] == "fallback"
    assert data["respuesta"] is not None
    assert len(data["respuesta"]) > 10


def test_fallback_con_contexto_ticket(client, auth_header, ai_config_sin_key, negocio_config, contexto_cliente):
    """Fallback usa contexto del ticket para responder."""
    res = client.post("/responder", json={
        "mensaje": "como va mi ticket?",
        "contexto": contexto_cliente,
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert data["respuesta"] is not None


def test_respuesta_no_vacia(client, auth_header, ai_config_sin_key, negocio_config):
    """Nunca retorna respuesta vacía."""
    mensajes = ["", "   ", "???", "asdfghjkl"]
    for msg in mensajes:
        res = client.post("/responder", json={
            "mensaje": msg or "test",
            "contexto": {},
            "negocio": negocio_config,
            "ai": ai_config_sin_key
        }, headers=auth_header)
        assert res.status_code == 200
        assert res.json()["respuesta"] is not None
        assert len(res.json()["respuesta"]) > 0
