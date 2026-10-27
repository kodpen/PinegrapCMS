---
name: pg-chips
description: Chips fields (data-pg-chips) on Bootstrap 6 Chips since B9a (#2208): the box drawn after the hidden input, silent fill, change back into the input, the translated Remove label, init timing
---

## How is a chips field drawn?
- `drawChips()` in `assets/js/pg_fields.js` puts a `.chip-input.pg-chips` box after the posted input (class `tagin` keeps that input `display: none`): a `.chip` per stored value and a `.form-ghost` input (placeholder from `data-placeholder`, disabled with the input). Then `pgUI.chips.get(box, {separator})` creates Chips.
- The box takes the input's classes except `tagin` and `form-control(-sm/-lg)`; `form-control-sm` adds `pg-chips-sm` (designer keyword box). Panel look in `backend.src.css` (`.pg-chips`), dark editor dialog in `style_designer.css`.

## Why are the chips in the markup before Chips is created?
- Chips posts nothing and announces only through `change.bs.chips`. Filling it with `add()` would fire change for a stored value and mark the settings dialog dirty. Chips reads markup chips silently.
- Every add or remove writes the joined list into the input and fires its native bubbling `change`. `pgChips.set()` writes the input, fires one change, disposes the box and draws it again.

## Where does the remove button's label come from?
- Bootstrap hard-codes `aria-label="Remove"` on `.chip-dismiss`. `pg_fields_script()` prints `data-pg-remove-label="<lang('Remove')>"` on its tag; the engine relabels every button after drawing and after each change.

## When is it drawn?
- Chips' data API reads only `[data-bs-chips]` on DOMContentLoaded: never put that attribute on a field. `pgFields.init()` draws on DOMContentLoaded (the bridge module has run by then); a field met before `window.bootstrap` is set waits for `pgUI.ready()`. Injected markup: `pgInitInjected()` calls `pgFields.init(scope)`.
- The ghost input is never disabled for `readOnly`: the settings dialog latches every text field read-only until someone reaches it, the hidden input too.

## What went with tagin?
- `tagin()` left `backend.src.js`, its CSS left `backend.src.css`; the add/edit menu's `<ul class="…">` preview uses a plain `.chip`. Tests: `tests/pg_fields_test.php` (`test_pg_fields_chips_on_bootstrap_6`).
