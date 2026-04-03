#!/bin/bash
# ============================================================
# Test PHP: Arquitectura + Seguridad + Type Strings
# Ejecutar: bash scripts/test_php.sh
# ============================================================

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$SCRIPT_DIR")"
FAILED=0

echo "========================================"
echo "  PHP TESTS — SSolutions QA Pipeline"
echo "========================================"
echo ""

# 1. Multi-tenant isolation
echo "--- [1/3] Multi-Tenant Isolation ---"
if php "$ROOT/tests/arquitectura/test_multitenant.php"; then
    echo "PASS"
else
    echo "FAIL"
    FAILED=1
fi
echo ""

# 2. Type string validation
echo "--- [2/3] Type String Validation ---"
if php "$ROOT/tests/arquitectura/test_type_strings.php"; then
    echo "PASS"
else
    echo "FAIL"
    FAILED=1
fi
echo ""

# 3. Security tests
echo "--- [3/3] Security ---"
if php "$ROOT/tests/arquitectura/test_seguridad.php"; then
    echo "PASS"
else
    echo "FAIL"
    FAILED=1
fi
echo ""

# Resultado
echo "========================================"
if [ $FAILED -eq 0 ]; then
    echo "  ALL PHP TESTS PASSED"
else
    echo "  SOME PHP TESTS FAILED"
fi
echo "========================================"

exit $FAILED
