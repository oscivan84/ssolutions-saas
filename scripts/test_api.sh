#!/bin/bash
# ============================================================
# Test API: FastAPI microservicio de IA (pytest)
# Ejecutar: bash scripts/test_api.sh
# ============================================================

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$SCRIPT_DIR")"

echo "========================================"
echo "  API TESTS — SSolutions AI Service"
echo "========================================"
echo ""

cd "$ROOT"

# Instalar deps si no están
pip install -q pytest httpx 2>/dev/null || true

# Correr tests
echo "--- Running pytest ---"
python -m pytest tests/api/ tests/caos/ -v --tb=short

echo ""
echo "========================================"
echo "  API TESTS COMPLETE"
echo "========================================"
