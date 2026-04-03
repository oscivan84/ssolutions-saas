"""Test: Endpoint /diagnostico — Resumen de diagnóstico."""


def test_diagnostico_fallback(client, auth_header, ai_config_sin_key, negocio_config, datos_diagnostico):
    """Sin API key genera resumen local."""
    res = client.post("/diagnostico", json={
        "datos": datos_diagnostico,
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert data["source"] == "fallback"
    assert "procesador" in data["respuesta"].lower() or "cpu" in data["respuesta"].lower()
    assert "ram" in data["respuesta"].lower() or "memoria" in data["respuesta"].lower()


def test_diagnostico_datos_vacios(client, auth_header, ai_config_sin_key, negocio_config):
    """Con datos vacíos no crashea."""
    res = client.post("/diagnostico", json={
        "datos": {},
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["respuesta"] is not None
