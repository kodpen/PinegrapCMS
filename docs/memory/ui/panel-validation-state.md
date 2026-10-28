---
name: panel-validation-state
description: Invalid and valid fields in the panel on Bootstrap 6: why v6's own state was invisible, the panel rules on --pg-danger/--pg-success, focus ring, what stays on the bridge
---

## How do invalid and valid fields get their colour?
- Bootstrap 6 draws `.is-invalid` / `.is-valid` only through `--bs-danger-border` / `--bs-success-border` (no icon); the bridge fills those with the pale `--pg-*-line` tones, which alerts, badges and `theme-*` borders need, so a marked field looked unmarked and turned blue on focus.
- Panel rules in the form-controls section of `backend.src.css` (#PR) give `.form-control`, `.form-adorn:has(.form-ghost…)`, `.chip-input:has(.form-ghost…)`, `.check`/`.radio` and `.switch:has(input…)` a `--pg-danger` / `--pg-success` border, kept on focus with a ring in the same tone at `--pg-focus-ring-width`. The v5 `--bs-form-(in)valid-*` lines went: v6 never read them. `tests/bs6_validation_state_test.php` pins them.
- Hit by every server error (`liveform::create_error_class()`) and the runtime marks of `product_builder.js`, `add_order.js`, `erp_account_quick.js` and `backend.src.js` (IndexNow key). The `.tagin.is-invalid + .pg-chips` rule had the same look already.

## What must stay?
- `--bs-danger-border` / `--bs-success-border` on the pale `-line` tones (alerts, badges, `theme-*` borders) and every `--pg-*` value. A `.form-adorn` with an invalid input takes the 3px ring of `--pg-focus-ring-width`, not the global rule's 0.2rem; ring sizes otherwise wait for B10 (`panel-focus-ring.md`).
- Computed (Chromium, v6 dist + backend.src.css): invalid border 255,205,210 to 229,115,115 light, 107,43,43 to 229,115,115 dark; focused, it kept that border (was 100,181,246) with a danger ring at 35%.

Related: docs/memory/ui/panel-css-tokens.md, docs/memory/ui/panel-focus-ring.md, docs/memory/ui/form-adorn.md
