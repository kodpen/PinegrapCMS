---
name: translation-rules
description: Rule that every user-visible string goes through lang() with a matching tr.json key (English source key, Turkish value), and the same rule for designer JS.
---

## What must be wrapped in lang()?
- Every user-visible string is wrapped in `lang('...')`.
- The `lang()` argument MUST exist as a key in `includes/local/tr.json`. When you add a new string, add it to `tr.json` too.
- The same rule applies to the visual designer's JavaScript through `_sdT('...')` (see `docs/memory/core/sdt-designer-js-text-rules.md`).

## How is a tr.json key built?
- The key is always the English source string, placeholders included. The value is the Turkish text. Variables stay in the key exactly as written:
```json
"{var:1} is required": "{var:1} gereklidir",
"{var:1|c} is required": "{var:1|c} gereklidir"
```
- `lang('X')` returns the tr.json value, or the English string if the key is missing.

Related: docs/memory/core/lang-function-syntax.md
