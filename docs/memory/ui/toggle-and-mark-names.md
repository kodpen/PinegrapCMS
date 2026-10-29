---
name: toggle-and-mark-names
description: Toggle aria-labels follow state, icon marks carry visually-hidden text, radio groups get a legend or radiogroup name, reasons are printed not hovered (offers, key codes, ERP)
---

## How does a toggle whose label flips keep its spoken name right?
- The button prints `aria-label` beside its `title` (both translated). On load `pgInjectAriaAndRequiredMarkers()` and Bootstrap's popover copy the title to `aria-label` once and never again, so the script that flips the label sets `title`, `data-bs-original-title` **and** `aria-label` (`relabel()` in `templates/view_offers.php`). Otherwise a screen reader keeps the first name after a click.

## How is an icon-only badge or a list mark read?
- The icon is `aria-hidden="true"` and a `<span class="visually-hidden">` carries the name: the offers list's up-sell badge (`Up-sell message`), the key codes list's Single-Use and Enabled marks (`Yes`). A `title` on a non-focusable span is a hover popover only; a bare `task_alt` ligature is read as "task_alt".
- `pg_key_code_list_cells()` (`includes/fn/ecommerce.php`) draws the Enabled mark for the list and for the quick-edit answer alike, so the text is set there once.

## How are radios with a heading named as a group?
- `<fieldset>` with `<legend class="form-label fs-md">`, not a `<label for="">`: an empty `for` names nothing and makes `pgInjectAriaAndRequiredMarkers()` skip the label (the key code form's Report radios).

## Where do the ERP screens keep a reason or a mark's details?
- Never only in a `title`: `pgBindTitlePopovers()` shows it on hover, Bootstrap 6 moves it to `data-bs-original-title`, and an `aria-label` on a bare `<i>` (no role) is ignored. Marks follow the rule above: the accounts list's e-document triangle and the Over limit badge's limit and excess, the invoices list's snoozed reminders bell, the Signed pen (quotes list, quote heading).
- The invoice's cancel reasons (period lock, provider) are a `<p id="erp_cancel_note">` under the button bar, tied with `aria-describedby`; the disabled button is no longer wrapped in a focusable span. A repeating expense's last error wraps (`text-break`) instead of being cut.
- Account sync: each differing field's three radios sit in `role="radiogroup"` named by the field label. The accountant link's Copy button keeps its label; only the icon changes and a polite `role="status"` says Copied.

## Why do the Site Settings cards name everything in their own markup?
- Each pane arrives over Ajax and `pgInitInjected()` does not run the label binder or the required marker of `pgInjectAriaAndRequiredMarkers()`, so a label without `for` names nothing there (#2995).
- 18 radio groups are `<fieldset>` + `<legend class="form-label fs-md">` inside their cell (the social networking Setup legend holds only its two radios, not the blocks they open); radio cells sit outside the `:has()` row rules, so the layout did not change. `Current Site Time` names no control and is a `div.form-label`.
- The Cron Jobs dialog's command boxes are `aria-labelledby` their `<h5 id="cron_job_*_title">`; the card's dispatch box has an `aria-label`; Custom CSS and Default Barcode Type labels carry `for`.
- A hand-written marker is `<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span>` after the text. The e-document fields get `aria-required="true"`, never `required`: they sit in a collapsed block and the browser would refuse the whole save.

Test: `tests/offers_keycodes_names_test.php`, `tests/erp_marks_names_test.php`, `tests/settings_card_names_test.php`.
