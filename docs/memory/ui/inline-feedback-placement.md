---
name: inline-feedback-placement
description: Where a panel field's error message goes on Bootstrap 6 (pgPlaceInlineFeedback(), #2578) and how script-set marks get aria-invalid and their message (pgFieldInvalid(), class observer, #3026)
---

## Why does the message not sit right after the field?
- liveform prints `<div class="invalid-feedback d-block pg-inline-feedback">` after a field with a server error, and the injector made one the same way from `window.__pgFieldErrors`. Inside a v6 `.input-group` (no wrap), a `.form-adorn` label, a `.chip-input` or a `.switch` that message became a flex item and squeezed the field to a sliver.

## What does the panel do now?
- `pgPlaceInlineFeedback(el, errs)` in `backend.src.js`, called by `pgInjectAriaAndRequiredMarkers()` for every `.is-invalid` field: for a field whose closest wrapper is one of the four, the `.pg-inline-feedback` after the field moves to just after the wrapper; a `.switch` inside a `.form-field` puts it at the end of the `.form-field`. Without a message on the page, one is made from `errs` and placed the same way, with `aria-describedby`.
- It runs from the `output_errors()` inline script and again on `DOMContentLoaded`: a message already after the wrapper is kept and an inner duplicate removed, `data-pg-feedback-injected` marks the field done.
- A page's own static `.invalid-feedback` (no `pg-inline-feedback`) stays where the page put it.

## How do marks a script sets later reach a screen reader?
- Symptom (#3026): the injector set `aria-invalid` only when it ran, so a field a script marked later (product code already used, blank SKU on save, option/attribute/barcode/price-rule boxes, local sale and ERP quick-account dialogs) got the red class alone and was focused with no error read; a cleared mark kept no aria state.
- A `MutationObserver` on the document (`attributeFilter: ['class']`) calls `pgFieldAria()` for an `input`/`select`/`textarea` whose `is-invalid` came or went: `aria-invalid="true"` and the field's message id in `aria-describedby` (other ids kept) while invalid, both removed when the class goes. The message is a later sibling before the next control, else the one after the row wrapper (`pgFieldFeedback()`); one without an id gets one.
- `window.pgFieldInvalid(el, on, message)` marks or clears at once; with a message and none on the page it makes one (`data-pg-made`) where `pgPutFeedback()` puts it and removes it on clear. Scripts that only toggle the class need no change.
- Not covered: the Page Designer (`page_designer.php` loads no `backend.min.js`), so its region-name mark still has no `aria-invalid`.

## What about forms with their own message?
- A fixed `<div class="invalid-feedback">Required Area</div>` after a liveform field showed beside liveform's message (`.is-invalid ~ .invalid-feedback`): "already in use" next to "Required Area", or it twice. Removed from the shared forms of calendars, events, locations, currencies, menu items, product attributes, new product groups, ads, short links, campaign profiles, auto dialogs and key codes (`tests/required_boxes_forms_test.php`).
- Kept (key stays): folder, mass edit, contact group, submitted form, `product_builder.php` (`product_name_check.js` reads it) and hand-written forms.
- `includes/erp/account_quick.php`: the tax number message follows the GİB lookup group's `</div>`; out of reach of `.is-invalid ~ .invalid-feedback`, so `erp_account_quick.js` adds `d-block` with the text and removes it in `clearErrors()`.
- A new form that prints a message for a field in a wrapper puts it after the wrapper and shows it itself.

## How is it tested?
- `tests/bs6_inline_feedback_test.php` runs the placement and mark code under Node on a fake document (group, adorn run twice, switch in a form-field, no double, plain field, static message; mark/clear with a hint kept, group message linked, made message removed, a later control's message not borrowed) and pins the two forms.
