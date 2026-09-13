# Biblioteki zewnętrzne w katalogu vendor

Pliki w tym katalogu pochodzą z pakietów npm i są dołączone jako gotowe moduły ES,
bez kroku budowania (ADR-018). Nie modyfikujemy ich logiki — jedyną zmianą jest
przepisanie odwołań między pakietami na ścieżki plików, bo ładujemy je bezpośrednio,
a nie przez bundler.

| Plik | Pakiet | Wersja | Licencja |
|---|---|---|---|
| `preact.js` | preact | 10.29.8 | MIT |
| `preact-hooks.js` | preact/hooks | 10.29.8 | MIT |
| `signals-core.js` | @preact/signals-core | — | MIT |
| `signals.js` | @preact/signals | 2.11.2 | MIT |
| `htm.js` | htm | 3.1.1 | Apache-2.0 |

Łącznie ~10 KB gzip.

## Aktualizacja

```bash
npm install preact @preact/signals htm
cp node_modules/preact/dist/preact.module.js              assets/vendor/preact.js
cp node_modules/preact/hooks/dist/hooks.module.js         assets/vendor/preact-hooks.js
cp node_modules/@preact/signals-core/dist/signals-core.module.js assets/vendor/signals-core.js
cp node_modules/@preact/signals/dist/signals.module.js    assets/vendor/signals.js
cp node_modules/htm/dist/htm.module.js                    assets/vendor/htm.js
```

Po skopiowaniu trzeba przepisać odwołania `from"preact"` na `from"./preact.js"` —
robi to `tools/vendor-preact.sh`.
