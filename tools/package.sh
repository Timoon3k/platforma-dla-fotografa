#!/usr/bin/env bash
# Pakuje bieżący stan wtyczki Kadr do archiwum RAR (checkpoint końca sesji).
#
#   ./tools/package.sh              → dist/kadr-<wersja>-<data>.rar
#   ./tools/package.sh session2     → dist/kadr-<wersja>-session2.rar
#
# Do archiwum trafia wyłącznie to, co składa się na wtyczkę i jej dokumentację.
# Nigdy: .git, vendor, node_modules, dist, pliki lokalne i cokolwiek z sekretami.

set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT"

VERSION="$( grep -m1 '^\*\*Wersja:\*\*' PROJECT_STATE.md | sed -E 's/.*\*\*Wersja:\*\* *//; s/ .*//' )"
VERSION="${VERSION:-0.0.0}"
LABEL="${1:-$( date +%Y%m%d )}"
NAME="kadr-${VERSION}-${LABEL}"
OUT="dist/${NAME}.rar"

command -v rar >/dev/null 2>&1 || { echo "BŁĄD: brak polecenia 'rar' (apt-get install rar)"; exit 1; }

mkdir -p dist
rm -f "$OUT"

# -r rekurencyjnie · -ep1 bez nadrzędnej ścieżki · -m5 maksymalna kompresja
# -x wykluczenia · -t test integralności po spakowaniu
rar a -r -ep1 -m5 -t \
  -x'*/.git/*' -x'.git/*' \
  -x'*/node_modules/*' -x'*/vendor/*' \
  -x'dist/*' -x'*.rar' -x'*.zip' \
  -x'*.log' -x'.DS_Store' \
  -x'.env' -x'.env.*' -x'*.pem' -x'*.key' -x'auth.json' \
  "$OUT" . >/dev/null

echo "Archiwum:  $OUT"
echo "Rozmiar:   $( du -h "$OUT" | cut -f1 )"
echo "Pozycji:   $( rar lb "$OUT" | wc -l )"
