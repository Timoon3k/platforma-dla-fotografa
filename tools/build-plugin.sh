#!/usr/bin/env bash
# Buduje instalowalny pakiet wtyczki dla WordPressa.
#
#   ./tools/build-plugin.sh        → dist/kadr-<wersja>.zip
#
# Do pakietu trafia WYŁĄCZNIE to, co jest potrzebne w runtimie. Dokumentacja,
# testy, narzędzia i konfiguracja deweloperska zostają poza nim — wtyczka
# na produkcji nie ma powodu ich wozić.

set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$ROOT"

VERSION="$( grep -m1 "^const VERSION" kadr.php | sed -E "s/.*'([^']+)'.*/\1/" )"
VERSION="${VERSION:-0.0.0}"
STAGE="$( mktemp -d )"
OUT="dist/kadr-${VERSION}.zip"

# Katalog w archiwum musi nazywać się tak jak slug wtyczki.
mkdir -p "$STAGE/kadr"

# Elementy wymagane — brak któregokolwiek przerywa budowanie z komunikatem.
# Wzorzec `[ -e x ] && cp` przy `set -e` cicho ubija skrypt w połowie kopiowania,
# więc sprawdzamy jawnie.
REQUIRED=( kadr.php uninstall.php readme.txt src blocks assets )
OPTIONAL=( languages )

for item in "${REQUIRED[@]}"; do
	if [ ! -e "$item" ]; then
		echo "BŁĄD: brak wymaganego elementu: $item" >&2
		exit 1
	fi
	cp -R "$item" "$STAGE/kadr/"
done

for item in "${OPTIONAL[@]}"; do
	if [ -e "$item" ]; then
		cp -R "$item" "$STAGE/kadr/"
	fi
done

# Sanity: nic z tych rzeczy nie ma prawa znaleźć się w wydaniu.
find "$STAGE/kadr" \( -name '.DS_Store' -o -name '*.log' -o -name '.env*' \
	-o -name '*.pem' -o -name '*.key' \) -delete

mkdir -p dist
rm -f "$OUT"

( cd "$STAGE" && zip -qr "$ROOT/$OUT" kadr -x '*.DS_Store' )
rm -rf "$STAGE"

# Weryfikacja zawartości: bootstrap i nagłówek wtyczki muszą tam być.
# Zawartość odczytujemy RAZ do zmiennej.
# `unzip -l ... | grep -q` pod `set -o pipefail` daje fałszywy błąd: grep kończy
# się po pierwszym trafieniu, unzip dostaje SIGPIPE, a potok zwraca niezero
# mimo że plik jest w archiwum.
LISTING="$( unzip -l "$OUT" )"
BOOTSTRAP="$( unzip -p "$OUT" kadr/kadr.php )"

for required in kadr/kadr.php kadr/uninstall.php kadr/readme.txt \
	kadr/src/Infrastructure/WordPress/Plugin.php kadr/blocks/hero/block.json \
	kadr/assets/css/tokens.css kadr/assets/js/motion.js \
	kadr/assets/css/app.css kadr/assets/js/app/main.js \
	kadr/assets/js/app/form.js kadr/assets/js/app/drawer.js \
	kadr/assets/vendor/preact.js kadr/assets/vendor/signals.js \
	kadr/assets/vendor/htm.js kadr/assets/vendor/LICENSES.md; do
	case "$LISTING" in
		*"$required"*) ;;
		*) echo "BŁĄD: brak $required w pakiecie" >&2; exit 1 ;;
	esac
done

case "$BOOTSTRAP" in
	*"Plugin Name:"*) ;;
	*) echo "BŁĄD: brak nagłówka wtyczki" >&2; exit 1 ;;
esac

# Do wydania nie może trafić dokumentacja, testy ani narzędzia.
for forbidden in 'kadr/docs/' 'kadr/tests/' 'kadr/tools/' 'kadr/.claude/' 'kadr/dist/'; do
	case "$LISTING" in
		*"$forbidden"*) echo "BŁĄD: pakiet zawiera $forbidden" >&2; exit 1 ;;
	esac
done

echo "Pakiet:    $OUT"
echo "Wersja:    $VERSION"
echo "Rozmiar:   $( du -h "$OUT" | cut -f1 )"
echo "Plików:    $( unzip -l "$OUT" | tail -1 | awk '{print $2}' )"
