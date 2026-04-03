"""Test: Endpoint /score-cliente — Scoring caliente/tibio/frio."""


def test_score_fallback(client, auth_header, ai_config_sin_key):
    """Sin API key retorna tibio por defecto."""
    res = client.post("/score-cliente", json={
        "contexto": {"nombre": "Test"},
        "historial": "",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert data["score"] == "tibio"
    assert data["source"] == "fallback"


def test_sugerencia_tecnico_fallback(client, auth_header, ai_config_sin_key):
    """Sin API key retorna sugerencia genérica."""
    res = client.post("/sugerencia-tecnico", json={
        "ticket": {"codigo": "TKT-001", "estado": "abierto"},
        "diagnostico_data": {},
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert "mensaje_sugerido" in data
    assert data["source"] == "fallback"
