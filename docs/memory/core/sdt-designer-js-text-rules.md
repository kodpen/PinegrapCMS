---
name: sdt-designer-js-text-rules
description: How visible text in assets/js/style_designer.js must use _sdT('English') keys: literal-key rule, server key scan and cache, escaping, PHP/JS default twins.
---

## Why does the designer JS use _sdT()?
- `assets/js/style_designer.js` was Turkish-first, so after the software language moved to English the UI (and a dragged widget's initial content) stayed Turkish. Plan and tool (not in the repo; ask if needed): `docs/_plan_tasarimci_i18n.md` + `docs/_tools/designer_i18n.php`.

## What may not be a plain literal?
- NO text an operator or visitor reads may be a plain literal in this file: panel label, `row()`/`sect()` heading, toast, dialog, `customName`, placeholder, `aria-label`, palette component sample content, starting tree `text:` value.
- All of it is `_sdT('English text')`. The key is the English source text (same contract as `lang()`) and MUST exist in `tr.json`. The tr.json key audit covers this file too.

## What shape must the key have?
- The key is a single-quoted literal. NOT `_sdT("...")`, NOT `_sdT(variable)`, NOT `_sdT('a' + b)`.
- Reason: the server (`pg_designer_i18n_keys()`, `includes/fn/designer.php`) scans the file for the pattern `_sdT('...')`, passes only the keys it finds through `lang()` and sends them as `sdDesign.i18n` (`includes/designer_screen.php`).
- When the language is English the map is empty and `_sdT` returns the key as-is.
- The scan result is cached in `data/temp/designer_i18n_keys.json`, stamped with mtime:size.

## How do variables and escaping work?
- Variables enter as `{var}` / `{var:N}`, never embedded in the key.
- `_sdT` output is NOT escaped: where it goes into HTML use `esc(_sdT(...))`.

## What about fallbacks and PHP defaults?
- `_pgT(key, fallback)` was removed; a pattern that carries a Turkish fallback in code must not come back.
- PHP default trees and widget fallback texts use `lang()`; their JS twins (`_swTypeDefaults`, `_emptyDefault`) use `_sdT()` with the same key.

Related: docs/memory/core/designer-i18n-audit-tool.md
