"""Test: Health check y auth básica."""


def test_health(client):
    """El servicio responde OK."""
    res = client.get("/health")
    assert res.status_code == 200
    data = res.json()
    assert data["status"] == "ok"
    assert data["service"] == "ai-service"


def test_auth_requerida(client):
    """Endpoints rechazan sin X-Internal-Key."""
    res = client.post("/responder", json={"mensaje": "hola"})
    assert res.status_code == 401


def test_auth_key_invalida(client):
    """Endpoints rechazan key incorrecta."""
    res = client.post("/responder", json={"mensaje": "hola"}, headers={"X-Internal-Key": "wrong"})
    assert res.status_code == 401
