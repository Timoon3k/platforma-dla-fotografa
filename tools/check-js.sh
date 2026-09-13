#!/usr/bin/env bash
# Kontrola składni wszystkich plików JavaScript.
#
# `node --check` traktuje pliki .js jako CommonJS i wywala się na `import`,
# więc moduły ES sprawdzamy przez kopię z rozszerzeniem .mjs.
# Pliki w assets/vendor są pomijane — to kod zewnętrzny, nie nasz.

set -uo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT"

TMP="$( mktemp -d )"
trap 'rm -rf "$TMP"' EXIT

failed=0
checked=0

while IFS= read -r file; do
	checked=$(( checked + 1 ))

	if grep -qE '^[[:space:]]*(import|export)[[:space:]]' "$file"; then
		cp "$file" "$TMP/module.mjs"
		target="$TMP/module.mjs"
	else
		cp "$file" "$TMP/script.js"
		target="$TMP/script.js"
	fi

	if ! node --check "$target" 2>"$TMP/err"; then
		echo -e "\033[31m✗\033[0m $file"
		sed 's/^/    /' "$TMP/err" | head -4
		failed=$(( failed + 1 ))
	fi
done < <( find assets/js blocks -name '*.js' -not -path '*/vendor/*' | sort )

if [ "$failed" -gt 0 ]; then
	echo -e "\n\033[31mBłędy składni: $failed z $checked plików.\033[0m"
	exit 1
fi

echo -e "\033[32mSkładnia JS poprawna: $checked plików.\033[0m"
