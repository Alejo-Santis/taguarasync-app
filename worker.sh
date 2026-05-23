#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
#  Taguara Sync — Queue Worker (entorno local / pruebas)
#  Uso: ./worker.sh
#  Detener: Ctrl+C
# ─────────────────────────────────────────────────────────────

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m'

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Taguara Sync · Queue Worker${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${CYAN}  Jobs que procesa:${NC}"
echo    "    • EmitElectronicInvoiceJob  — emite facturas DIAN"
echo    "    • EmitCreditNoteJob         — emite notas crédito"
echo    "    • CheckDianStatusJob        — consulta estado DIAN"
echo ""
echo -e "${YELLOW}  Reintentos:  3   |   Timeout:  90s${NC}"
echo -e "${YELLOW}  Cola:        database${NC}"
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Verificar que hay jobs pendientes antes de arrancar
PENDING=$(php artisan tinker --execute 'echo \DB::table("jobs")->count();' 2>/dev/null || echo "?")
echo -e "  Jobs pendientes en cola: ${YELLOW}${PENDING}${NC}"
echo ""

trap 'echo ""; echo -e "${RED}  Worker detenido.${NC}"; echo ""' EXIT

php artisan queue:work \
    --queue=default \
    --tries=3 \
    --timeout=90 \
    --sleep=2 \
    --rest=1 \
    --memory=256 \
    --verbose
