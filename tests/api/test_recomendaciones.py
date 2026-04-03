"""Test: Endpoint /recomendaciones — Recomendaciones técnicas."""


def test_recomendaciones_fallback(client, auth_header, ai_config_sin_key, negocio_config, datos_diagnostico):
    """Sin API key genera recomendaciones locales."""
    res = client.post("/recomendaciones", json={
        "datos": datos_diagnostico,
        "tipo_servicio": "remoto",
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    data = res.json()
    assert data["source"] == "fallback"
    assert isinstance(data["recomendaciones"], list)
    assert len(data["recomendaciones"]) > 0
    # Cada recomendación tiene estructura correcta
    for r in data["recomendaciones"]:
        assert "accion" in r
        assert "prioridad" in r
        assert r["prioridad"] in ("alta", "media", "baja")


def test_recomendaciones_equipo_sano(client, auth_header, ai_config_sin_key, negocio_config):
    """Equipo sin problemas → mantenimiento preventivo."""
    res = client.post("/recomendaciones", json={
        "datos": {
            "cpu": {"uso_porcentaje": 20},
            "ram": {"uso_porcentaje": 40},
            "disco": {"uso_porcentaje": 30}
        },
        "negocio": negocio_config,
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    recs = res.json()["recomendaciones"]
    assert any("preventivo" in r["accion"].lower() for r in recs)
