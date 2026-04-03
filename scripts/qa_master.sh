#!/bin/bash
# ============================================================
# QA MASTER — SSolutions Pre-Deploy Validator
#
# Ejecuta las 4 capas de QA automáticamente.
# Si CUALQUIER capa crítica falla → EXIT 1 → deploy bloqueado.
#
# Uso:
#   bash scripts/qa_master.sh           # Full QA
#   bash scripts/qa_master.sh --quick   # Solo checks críticos (30s)
#
# En GitHub Actions: si exit code != 0, deploy se bloquea.
# ============================================================

set -o pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$SCRIPT_DIR")"
AI_URL="${AI_SERVICE_URL:-http://127.0.0.1:8100}"
QUICK_MODE=false
[[ "$1" == "--quick" ]] && QUICK_MODE=true

# Contadores
CRITICAL=0
HIGH=0
MEDIUM=0
PASS=0
SKIP=0

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_pass()     { echo -e "  ${GREEN}[PASS]${NC} $1"; ((PASS++)); }
log_fail()     { echo -e "  ${RED}[FAIL]${NC} $1"; }
log_critical() { echo -e "  ${RED}[CRITICAL]${NC} $1"; ((CRITICAL++)); }
log_high()     { echo -e "  ${YELLOW}[HIGH]${NC} $1"; ((HIGH++)); }
log_medium()   { echo -e "  ${CYAN}[MEDIUM]${NC} $1"; ((MEDIUM++)); }
log_skip()     { echo -e "  [SKIP] $1"; ((SKIP++)); }

echo ""
echo "========================================================"
echo "  SSolutions QA MASTER — Pre-Deploy Validator"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "  Mode: $( $QUICK_MODE && echo 'QUICK (critical only)' || echo 'FULL' )"
echo "========================================================"

# ============================================================
# CAPA 1: QA ESTATICO — Código sin ejecutar
# ============================================================
echo ""
echo -e "${CYAN}--- CAPA 1: QA ESTATICO (código) ---${NC}"

# 1.1 Verificar que todas las queries de tablas tenant tienen negocio_id
echo "  Checking multi-tenant isolation..."
TENANT_TABLES="diagnosticos|tickets|historial_estados_ticket|mantenimientos|mensajes_whatsapp|conversaciones_whatsapp|plantillas_mensaje|configuracion_soporte|eventos|jobs|automatizaciones|score_clientes|notificaciones|audit_log"

LEAKS=0
for f in "$ROOT"/model/*.php "$ROOT"/services/*.php "$ROOT"/ajax/*.php; do
    fname=$(basename "$f")
    # Saltar archivos que operan internamente (queries por PK, no listados)
    [[ "$fname" == "LogService.php" || "$fname" == "tenant.php" || "$fname" == "JobQueue.php" || "$fname" == "EventService.php" ]] && continue

    while IFS= read -r line; do
        # Buscar FROM/UPDATE/DELETE en tablas tenant sin negocio_id en contexto cercano
        if echo "$line" | grep -qiE "FROM\s+($TENANT_TABLES)\b|UPDATE\s+($TENANT_TABLES)\b"; then
            # Verificar que negocio_id está en las ~3 líneas cercanas
            linenum=$(grep -n "$line" "$f" 2>/dev/null | head -1 | cut -d: -f1)
            if [ -n "$linenum" ]; then
                context=$(sed -n "$((linenum > 2 ? linenum - 2 : 1)),$((linenum + 4))p" "$f")
                # Queries por PK (WHERE id*= ?) son seguras
            if echo "$context" | grep -qiE "WHERE\s+id\w+\s*=\s*\?"; then continue; fi

            if ! echo "$context" | grep -qi "negocio_id"; then
                    echo "    LEAK: $fname:$linenum"
                    ((LEAKS++))
                fi
            fi
        fi
    done < <(grep -iE "FROM\s+|UPDATE\s+|DELETE\s+FROM" "$f" 2>/dev/null)
done

if [ $LEAKS -eq 0 ]; then
    log_pass "Multi-tenant: todas las queries filtran por negocio_id"
else
    log_critical "Multi-tenant: $LEAKS queries sin negocio_id"
fi

# 1.2 Verificar auth en AJAX de escritura
echo "  Checking auth on write endpoints..."
AUTH_MISSING=0
for f in "$ROOT"/ajax/TicketAjax.php "$ROOT"/ajax/ConfigAjax.php "$ROOT"/ajax/MantenimientoAjax.php; do
    fname=$(basename "$f")
    if ! grep -q "requireAuth" "$f" 2>/dev/null; then
        echo "    MISSING: $fname sin requireAuth()"
        ((AUTH_MISSING++))
    fi
done

if [ $AUTH_MISSING -eq 0 ]; then
    log_pass "Auth: AJAX handlers protegidos"
else
    log_critical "Auth: $AUTH_MISSING handlers sin protección"
fi

# 1.3 Verificar que ejecutarConsulta tiene validación de params
if grep -q "strlen.*count" "$ROOT/config/database.php" 2>/dev/null; then
    log_pass "DB: validación de param count activa"
else
    log_high "DB: sin validación de param count en ejecutarConsulta()"
fi

# 1.4 Verificar rate limit en endpoints públicos
for f in diagnostico.php ticket_auto.php; do
    if grep -q "checkRateLimit" "$ROOT/api/$f" 2>/dev/null; then
        log_pass "Rate limit: api/$f protegido"
    else
        log_high "Rate limit: api/$f sin protección"
    fi
done

# 1.5 Verificar webhook resuelve tenant
if grep -q "phone_number_id" "$ROOT/api/webhook_whatsapp.php" 2>/dev/null; then
    log_pass "Webhook: resuelve tenant desde phone_id"
else
    log_critical "Webhook: NO resuelve tenant — mensajes irán al negocio equivocado"
fi

if $QUICK_MODE && [ $CRITICAL -gt 0 ]; then
    echo ""
    echo -e "${RED}QUICK MODE: $CRITICAL CRITICAL issues found. ABORTING.${NC}"
    exit 1
fi

# ============================================================
# CAPA 2: QA API — FastAPI microservicio
# ============================================================
echo ""
echo -e "${CYAN}--- CAPA 2: QA API (FastAPI tests) ---${NC}"

# Verificar si pytest está disponible
if command -v python &>/dev/null && python -c "import pytest" 2>/dev/null; then
    echo "  Running pytest..."
    if python -m pytest "$ROOT/tests/api/" "$ROOT/tests/caos/" -v --tb=line -q 2>&1 | tail -5; then
        PYTEST_EXIT=${PIPESTATUS[0]}
        if [ $PYTEST_EXIT -eq 0 ]; then
            log_pass "FastAPI: todos los tests pasaron"
        else
            log_critical "FastAPI: tests fallaron (exit code $PYTEST_EXIT)"
        fi
    fi
else
    log_skip "pytest no disponible — instalar: pip install pytest httpx"
fi

if $QUICK_MODE; then
    echo ""
    echo "QUICK MODE: Skipping Capa 3 y 4"
else

# ============================================================
# CAPA 3: QA FUNCIONAL — Endpoints vivos
# ============================================================
echo ""
echo -e "${CYAN}--- CAPA 3: QA FUNCIONAL (endpoints vivos) ---${NC}"

# 3.1 AI Service health
AI_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$AI_URL/health" 2>/dev/null || echo "000")
if [ "$AI_STATUS" = "200" ]; then
    log_pass "AI Service: respondiendo en $AI_URL"

    # 3.2 Test respuesta rápida (saludo)
    RESP=$(curl -s -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d '{"mensaje":"hola","contexto":{},"negocio":{"empresa_nombre":"QATest","moneda_simbolo":"$","costo_hora_remoto":"25000","costo_hora_sitio":"40000","costo_hora_taller":"30000"},"ai":{"provider":"anthropic","api_key":"","model":""}}' 2>/dev/null)

    if echo "$RESP" | grep -q '"respuesta"'; then
        log_pass "AI /responder: retorna respuesta válida"
    else
        log_critical "AI /responder: no retorna respuesta"
    fi

    # 3.3 Test clasificación
    RESP=$(curl -s -X POST "$AI_URL/clasificar" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d '{"mensaje":"cuanto cuesta?","ai":{"provider":"anthropic","api_key":"","model":""}}' 2>/dev/null)

    if echo "$RESP" | grep -q "CONSULTAR_PRECIO"; then
        log_pass "AI /clasificar: clasificación correcta"
    else
        log_high "AI /clasificar: clasificación incorrecta"
    fi

    # 3.4 Test auth requerida
    AUTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -d '{"mensaje":"hola"}' 2>/dev/null)

    if [ "$AUTH_STATUS" = "401" ]; then
        log_pass "AI auth: rechaza sin X-Internal-Key"
    else
        log_critical "AI auth: NO rechaza requests sin auth (HTTP $AUTH_STATUS)"
    fi
else
    log_skip "AI Service no disponible en $AI_URL"
fi

# ============================================================
# CAPA 4: QA CAOS — Inputs extremos via API
# ============================================================
echo ""
echo -e "${CYAN}--- CAPA 4: QA CAOS (inputs extremos) ---${NC}"

if [ "$AI_STATUS" = "200" ]; then
    HEADERS="-H 'Content-Type: application/json' -H 'X-Internal-Key: ss-internal-dev-key'"
    BASE_BODY=',"contexto":{},"negocio":{"empresa_nombre":"Test","moneda_simbolo":"$","costo_hora_remoto":"25000","costo_hora_sitio":"40000","costo_hora_taller":"30000"},"ai":{"provider":"anthropic","api_key":"","model":""}}'

    # 4.1 Mensaje largo (10K chars)
    LONG_MSG=$(python -c "print('x' * 10000)" 2>/dev/null || echo "xxxxxxxxxx")
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d "{\"mensaje\":\"$LONG_MSG\"$BASE_BODY" 2>/dev/null)

    if [ "$STATUS" = "200" ]; then
        log_pass "Caos: mensaje 10K chars — no crashea"
    else
        log_high "Caos: mensaje largo retorna HTTP $STATUS"
    fi

    # 4.2 XSS attempt
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d "{\"mensaje\":\"<script>alert(1)</script>\"$BASE_BODY" 2>/dev/null)

    if [ "$STATUS" = "200" ]; then
        log_pass "Caos: XSS attempt — no crashea"
    else
        log_medium "Caos: XSS attempt retorna HTTP $STATUS"
    fi

    # 4.3 Contexto con null values
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$AI_URL/responder" \
        -H "Content-Type: application/json" \
        -H "X-Internal-Key: ss-internal-dev-key" \
        -d '{"mensaje":"test","contexto":{"nombre_cliente":null,"estado":null},"negocio":{"empresa_nombre":"T","moneda_simbolo":"$","costo_hora_remoto":"0","costo_hora_sitio":"0","costo_hora_taller":"0"},"ai":{"provider":"anthropic","api_key":"","model":""}}' 2>/dev/null)

    if [ "$STATUS" = "200" ]; then
        log_pass "Caos: null context values — no crashea"
    else
        log_medium "Caos: null context retorna HTTP $STATUS"
    fi
else
    log_skip "Caos tests: AI Service no disponible"
fi

fi  # end of non-quick mode

# ============================================================
# RESULTADO FINAL
# ============================================================
echo ""
echo "========================================================"
TOTAL=$((PASS + CRITICAL + HIGH + MEDIUM + SKIP))
echo "  RESULTADOS: $TOTAL checks ejecutados"
echo ""
echo -e "  ${GREEN}PASS:${NC}     $PASS"
echo -e "  ${RED}CRITICAL:${NC} $CRITICAL"
echo -e "  ${YELLOW}HIGH:${NC}     $HIGH"
echo -e "  ${CYAN}MEDIUM:${NC}   $MEDIUM"
echo -e "  SKIP:     $SKIP"
echo ""

if [ $CRITICAL -gt 0 ]; then
    echo -e "  ${RED}========================================${NC}"
    echo -e "  ${RED}  VEREDICTO: NO DESPLEGAR${NC}"
    echo -e "  ${RED}  $CRITICAL issues CRITICOS encontrados${NC}"
    echo -e "  ${RED}========================================${NC}"
    exit 1
elif [ $HIGH -gt 0 ]; then
    echo -e "  ${YELLOW}========================================${NC}"
    echo -e "  ${YELLOW}  VEREDICTO: LISTO CON RIESGOS${NC}"
    echo -e "  ${YELLOW}  $HIGH issues de alta severidad${NC}"
    echo -e "  ${YELLOW}========================================${NC}"
    exit 0  # No bloquea pero advierte
else
    echo -e "  ${GREEN}========================================${NC}"
    echo -e "  ${GREEN}  VEREDICTO: LISTO PARA PRODUCCION${NC}"
    echo -e "  ${GREEN}========================================${NC}"
    exit 0
fi
