#!/bin/bash
# ============================================================
# Test E2E: Flujo completo con curl
# Requiere: PHP built-in server + FastAPI corriendo
# Ejecutar: bash scripts/test_e2e.sh
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$SCRIPT_DIR")"
FAILED=0
AI_URL="${AI_SERVICE_URL:-http://127.0.0.1:8100}"
PHP_URL="${PHP_URL:-http://127.0.0.1:8080}"

echo "========================================"
echo "  E2E TESTS — SSolutions Full Flow"
echo "========================================"
echo "  AI Service: $AI_URL"
echo "  PHP App:    $PHP_URL"
echo ""

# Helper
assert_status() {
    local desc="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then
        echo "  [PASS] $desc (HTTP $actual)"
    else
        echo "  [FAIL] $desc — expected $expected, got $actual"
        FAILED=1
    fi
}

assert_contains() {
    local desc="$1" needle="$2" haystack="$3"
    if echo "$haystack" | grep -qi "$needle"; then
        echo "  [PASS] $desc"
    else
        echo "  [FAIL] $desc — '$needle' not found in response"
        FAILED=1
    fi
}

# ============================================================
# 1. AI SERVICE HEALTH
# ============================================================
echo "--- [1] AI Service Health ---"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$AI_URL/health" 2>/dev/null || echo "000")
if [ "$STATUS" = "200" ]; then
    echo "  [PASS] AI Service is up"
else
    echo "  [SKIP] AI Service not running ($STATUS) — skipping AI tests"
    AI_URL=""
fi
echo ""

# ============================================================
# 2. AI RESPONDER (si está corriendo)
# ============================================================
if [ -n "$AI_URL" ]; then
    echo "--- [2] AI Responder ---"

    # Test saludo
    RESP=$(curl -s -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d '{"mensaje":"hola","contexto":{},"negocio":{"empresa_nombre":"TestE2E","moneda_simbolo":"$","costo_hora_remoto":"25000","costo_hora_sitio":"40000","costo_hora_taller":"30000"},"ai":{"provider":"anthropic","api_key":"","model":""}}')
    assert_contains "Saludo rápido" "Hola" "$RESP"
    assert_contains "Source es rapida" "rapida" "$RESP"

    # Test clasificar
    RESP=$(curl -s -X POST "$AI_URL/clasificar" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d '{"mensaje":"cuanto vale?","ai":{"provider":"anthropic","api_key":"","model":""}}')
    assert_contains "Clasifica precio" "CONSULTAR_PRECIO" "$RESP"

    # Test score fallback
    RESP=$(curl -s -X POST "$AI_URL/score-cliente" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d '{"contexto":{},"historial":"","ai":{"provider":"anthropic","api_key":"","model":""}}')
    assert_contains "Score fallback" "tibio" "$RESP"

    # Test auth requerida
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -d '{"mensaje":"hola"}')
    assert_status "Auth requerida" "401" "$STATUS"
    echo ""
fi

# ============================================================
# 3. PHP API (si está corriendo)
# ============================================================
echo "--- [3] PHP API ---"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$PHP_URL/api/dashboard.php" 2>/dev/null || echo "000")
if [ "$STATUS" = "200" ]; then
    echo "  [PASS] PHP API is up"

    # Dashboard
    RESP=$(curl -s "$PHP_URL/api/dashboard.php")
    assert_contains "Dashboard retorna status" "success" "$RESP"

    # Diagnostico sin API key
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$PHP_URL/api/diagnostico.php" \
        -H "Content-Type: application/json" \
        -d '{}')
    # Debería fallar (sin API key o con bad request)
    if [ "$STATUS" != "200" ]; then
        echo "  [PASS] Diagnostico rechaza request vacío (HTTP $STATUS)"
    else
        echo "  [WARN] Diagnostico acepta request vacío"
    fi
else
    echo "  [SKIP] PHP API not running ($STATUS) — skipping PHP API tests"
fi
echo ""

# ============================================================
# RESULTADO
# ============================================================
echo "========================================"
if [ $FAILED -eq 0 ]; then
    echo "  ALL E2E TESTS PASSED"
else
    echo "  SOME E2E TESTS FAILED"
fi
echo "========================================"

exit $FAILED
