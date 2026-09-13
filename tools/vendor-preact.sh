#!/usr/bin/env bash
# Aktualizacja bibliotek frontendowych w assets/vendor (ADR-018).
#
# Dołączamy gotowe moduły ES, nie budujemy niczego. Ten skrypt kopiuje je
# z node_modules i przepisuje odwołania między pakietami na ścieżki plików,
# bo ładujemy je bezpośrednio przez import map, a nie przez bundler.

set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT"

command -v npm >/dev/null 2>&1 || { echo "BŁĄD: brak npm" >&2; exit 1; }

npm install --no-save --no-audit --no-fund preact @preact/signals htm

declare -A FILES=(
	["node_modules/preact/dist/preact.module.js"]="assets/vendor/preact.js"
	["node_modules/preact/hooks/dist/hooks.module.js"]="assets/vendor/preact-hooks.js"
	["node_modules/@preact/signals-core/dist/signals-core.module.js"]="assets/vendor/signals-core.js"
	["node_modules/@preact/signals/dist/signals.module.js"]="assets/vendor/signals.js"
	["node_modules/htm/dist/htm.module.js"]="assets/vendor/htm.js"
)

mkdir -p assets/vendor

for source in "${!FILES[@]}"; do
	[ -f "$source" ] || { echo "BŁĄD: brak $source" >&2; exit 1; }
	cp "$source" "${FILES[$source]}"
done

# Odwołania po nazwach pakietów → ścieżki plików.
for file in assets/vendor/*.js; do
	sed -i \
		-e 's|from"preact/hooks"|from"./preact-hooks.js"|g' \
		-e 's|from"@preact/signals-core"|from"./signals-core.js"|g' \
		-e 's|from"preact"|from"./preact.js"|g' \
		"$file"
done

echo "Zaktualizowano assets/vendor ($( cat assets/vendor/*.js | gzip -c | wc -c ) B gzip)."
echo "Pamiętaj o aktualizacji wersji w assets/vendor/LICENSES.md."
