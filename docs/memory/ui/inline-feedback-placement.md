---
name: inline-feedback-placement
description: Where a panel field's error message goes on Bootstrap 6: pgPlaceInlineFeedback() moves or makes it after an input-group, form-adorn, chip-input or switch; forms that print their own message (#2578)
---

## Why does the message not sit right after the field?
- liveform prints `<div class="invalid-feedback d-block pg-inline-feedback">` after a field with a server error, and the injector made one the same way from `window.__pgFieldErrors`. Inside a v6 `.input-group` (no wrap), a `.form-adorn` label, a `.chip-input` or a `.switch` that message became a flex item and squeezed the field to a sliver.

## What does the panel do now?
- `pgPlaceInlineFeedback(el, errs)` in `backend.src.js`, called by `pgInjectAriaAndRequiredMarkers()` for every `.is-invalid` field: for a field whose closest wrapper is one of the four, the `.pg-inline-feedback` after the field moves to just after the wrapper; a `.switch` inside a `.form-field` puts it at the end of the `.form-field`. Without a message on the page, one is made from `errs` and placed the same way, with `aria-describedby`.
- It runs from the `output_errors()` inline script and again on `DOMContentLoaded`: a message already after the wrapper is kept and an inner duplicate removed, `data-pg-feedback-injected` marks the field done.
- A page's own static `.invalid-feedback` (no `pg-inline-feedback`) stays where the page put it.

## What about forms with their own message?
- `includes/forms/short_link_form.php`: the static "Required Area" inside the name's input-group is gone (liveform's message covers it).
- `includes/erp/account_quick.php`: the tax number message follows the GİB lookup group's `</div>`; out of reach of `.is-invalid ~ .invalid-feedback`, so `erp_account_quick.js` adds `d-block` with the text and removes it in `clearErrors()`.
- A new form that prints a message for a field in a wrapper puts it after the wrapper and shows it itself.

## How is it tested?
- `tests/bs6_inline_feedback_test.php` runs the placement code under Node on a fake document (group, adorn run twice, switch in a form-field, no double, plain field, static message) and pins the two forms.
