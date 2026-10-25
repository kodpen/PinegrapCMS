---
name: sdt-designer-js-text-rules
description: How visible text in assets/js/style_designer.js must use _sdT('English') keys: literal-key rule, server key scan and cache, variables instead of built keys, escaping, PHP/JS default twins, the checks.
---

## Why does the designer JS use _sdT()?
- `assets/js/style_designer.js` was Turkish-first, so after the software language moved to English the UI (and a dragged widget's initial content) stayed Turkish. The conversion plan (`docs/_plan_tasarimci_i18n.md`) and its tool are not in the repository (`designer-i18n-audit-tool.md`).

## What may not be a plain literal?
- NO text an operator or visitor reads may be a plain literal in this file: panel label, `row()`/`sect()` heading, toast, dialog, `customName`, placeholder, `aria-label`, palette component sample content (prices included), starting tree `text:` value.
- All of it is `_sdT('English text')`. The key is the English source text (same contract as `lang()`) and MUST exist in the Turkish shards (`includes/local/tr/`, added with `php tools/tr_add.php`).

## What shape must the key have?
- The key is a single-quoted literal. NOT `_sdT("...")`, NOT `_sdT(variable)`, NOT `_sdT('a' + b)`.
- Reason: the server (`pg_designer_i18n_keys()`, `includes/fn/designer.php`) scans the file for the pattern `_sdT('...')`, passes only the keys it finds through `lang()` and sends them as `sdDesign.i18n` (`includes/designer_screen.php`). Any other form ships untranslated without a sign.
- When the language is English the map is empty and `_sdT` returns the key as-is.
- The scan result is cached in `data/temp/designer_i18n_keys.json`, stamped with mtime:size.

## How do variables and escaping work?
- `_sdT(key, vars)`: `vars` is one value or an array filling `{var}` / `{var:N}`. Never build the key: no number, name or Turkish word in it (`_sdT('Tab {var}', 1)`, not `'Tab 1'`; `_sdT('It stays in {var} as it was.', owner)`). A built key is a different key for every value and is never translated.
- `_sdT` output is NOT escaped: where it goes into HTML use `esc(_sdT(...))`.
- Prices, sample names and the data block that must be printed before the script: `designer/designer-sdt-translation.md`, `designer/designer-block-sample-content.md`.

## What about fallbacks and PHP defaults?
- `_pgT(key, fallback)` was removed; a pattern that carries a Turkish fallback in code must not come back.
- PHP default trees and widget fallback texts use `lang()`; their JS twins (`_swTypeDefaults`, `_emptyDefault`) use `_sdT()` with the same key.

## How is it checked?
- `php tools/check_lang.php` (CI) fails on a missing key and on any `_sdT()` call that is not a single-quoted literal. `node --check assets/js/style_designer.js` after editing it (`php -l` does not read it).
- A raw literal in a palette `label` or a `defaultProps` text is reported by no check (`check_lang.php` reads only `lang()` / `_sdT()` calls): verify by eye (`designer/designer-sdt-translation.md`).

Related: docs/memory/core/designer-i18n-audit-tool.md
