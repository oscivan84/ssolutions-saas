#!/bin/bash
# ============================================================
# QA QUICK — Checklist mínimo pre-deploy (30 segundos)
#
# Los 6 checks que NUNCA pueden fallar.
# Si uno falla → NO DESPLEGAR.
#
# Uso: bash scripts/qa_quick.sh
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$SCRIPT_DIR")"
FAIL=0

echo ""
echo "=== QA QUICK CHECKLIST ==="
echo ""

# 1. Se crea ticket correctamente (modelo tiene negocio_id en INSERT)
if grep -q "negocio_id" "$ROOT/model/Ticket.php" && grep -q "Tenant::id()" "$ROOT/model/Ticket.php"; then
    echo "  [OK] Ticket: usa negocio_id + Tenant::id()"
else
    echo "  [FAIL] Ticket: falta negocio_id"
    FAIL=1
fi

# 2. WhatsApp se envía (servicio existe y tiene método enviarTexto)
if grep -q "function enviarTexto" "$ROOT/services/WhatsAppService.php"; then
    echo "  [OK] WhatsApp: enviarTexto() existe"
else
    echo "  [FAIL] WhatsApp: método enviarTexto no encontrado"
    FAIL=1
fi

# 3. Bot responde (AIService tiene callService + fallback)
if grep -q "function responderWhatsApp" "$ROOT/services/AIService.php" && grep -q "respuestaBotLocal" "$ROOT/services/AIService.php"; then
    echo "  [OK] Bot: responderWhatsApp() + fallback existen"
else
    echo "  [FAIL] Bot: falta responderWhatsApp o fallback"
    FAIL=1
fi

# 4. Automatización 24h funciona (EventService emite + verifica automatizaciones)
if grep -q "verificarAutomatizaciones" "$ROOT/services/EventService.php" && grep -q "generarNotificacion" "$ROOT/services/EventService.php"; then
    echo "  [OK] Automatizaciones: emit + verify + notify"
else
    echo "  [FAIL] Automatizaciones: flujo incompleto"
    FAIL=1
fi

# 5. Worker procesa jobs (tiene switch con tipos de job)
if grep -q "enviar_whatsapp" "$ROOT/api/worker.php" && grep -q "scoring" "$ROOT/api/worker.php"; then
    echo "  [OK] Worker: maneja enviar_whatsapp + scoring"
else
    echo "  [FAIL] Worker: tipos de job faltantes"
    FAIL=1
fi

# 6. No hay errores de sintaxis PHP en archivos críticos
SYNTAX_ERRORS=0
for f in "$ROOT"/services/AIService.php "$ROOT"/services/TicketService.php "$ROOT"/services/ConversacionService.php "$ROOT"/config/tenant.php; do
    if command -v php &>/dev/null; then
        if ! php -l "$f" &>/dev/null; then
            echo "  [FAIL] Syntax error: $(basename $f)"
            SYNTAX_ERRORS=1
        fi
    fi
done
if command -v php &>/dev/null; then
    if [ $SYNTAX_ERRORS -eq 0 ]; then
        echo "  [OK] PHP syntax: sin errores en archivos críticos"
    else
        FAIL=1
    fi
else
    echo "  [SKIP] PHP syntax: php no disponible"
fi

echo ""
if [ $FAIL -eq 0 ]; then
    echo "  === ALL CHECKS PASSED — SAFE TO DEPLOY ==="
else
    echo "  === CHECKS FAILED — DO NOT DEPLOY ==="
fi
echo ""

exit $FAIL
