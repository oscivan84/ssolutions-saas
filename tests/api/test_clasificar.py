"""Test: Endpoint /clasificar — Clasificación de intención."""


def test_clasificar_aceptar(client, auth_header, ai_config_sin_key):
    """'sí acepto' → ACEPTAR_SERVICIO."""
    res = client.post("/clasificar", json={
        "mensaje": "si acepto, dale",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["intencion"] == "ACEPTAR_SERVICIO"


def test_clasificar_rechazar(client, auth_header, ai_config_sin_key):
    """'no gracias' → RECHAZAR_SERVICIO."""
    res = client.post("/clasificar", json={
        "mensaje": "no gracias, después",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["intencion"] == "RECHAZAR_SERVICIO"


def test_clasificar_precio(client, auth_header, ai_config_sin_key):
    """'cuánto cuesta' → CONSULTAR_PRECIO."""
    res = client.post("/clasificar", json={
        "mensaje": "cuanto cuesta el servicio?",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["intencion"] == "CONSULTAR_PRECIO"


def test_clasificar_estado(client, auth_header, ai_config_sin_key):
    """'como va mi ticket' → CONSULTAR_ESTADO."""
    res = client.post("/clasificar", json={
        "mensaje": "como va el avance de mi equipo?",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["intencion"] == "CONSULTAR_ESTADO"


def test_clasificar_saludo(client, auth_header, ai_config_sin_key):
    res = client.post("/clasificar", json={
        "mensaje": "hola buenos dias",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["intencion"] == "SALUDO"


def test_clasificar_desconocido(client, auth_header, ai_config_sin_key):
    """Mensaje incoherente → OTRO."""
    res = client.post("/clasificar", json={
        "mensaje": "xkcd 42 lorem ipsum",
        "ai": ai_config_sin_key
    }, headers=auth_header)
    assert res.status_code == 200
    assert res.json()["intencion"] == "OTRO"
