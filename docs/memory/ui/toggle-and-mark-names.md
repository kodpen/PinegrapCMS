---
name: toggle-and-mark-names
description: Toggle aria-labels follow state, icon marks carry visually-hidden text, radio groups get a legend or radiogroup name, reasons are printed not hovered (offers, key codes, ERP)
---

## How does a toggle whose label flips keep its spoken name right?
- The button prints `aria-label` beside its `title` (both translated). On load `pgInjectAriaAndRequiredMarkers()` and Bootstrap's popover copy the title to `aria-label` once and never again, so the script that flips the label sets `title`, `data-bs-original-title` **and** `aria-label` (`relabel()` in `templates/view_offers.php`). Otherwise a screen reader keeps the first name after a click.

## How is an icon-only badge or a list mark read?
- The icon is `aria-hidden="true"` and a `<span class="visually-hidden">` carries the name: the offers list's up-sell badge (`Up-sell message`), the key codes list's Single-Use and Enabled marks (`Yes`). A `title` on a non-focusable span is a hover popover only; a bare `task_alt` ligature is read as "task_alt".
- `pg_key_code_list_cells()` (`includes/fn/ecommerce.php`) draws the Enabled mark for the list and for the quick-edit answer alike, so the text is set there once.
- The form list view's two Hints buttons hide their `info` icon; the short link Name field hides its `public` icon and shows the address pattern as a `.form-text` instead of a hover popover on a span no keyboard reaches.

## How are radios with a heading named as a group?
- `<fieldset>` with `<legend class="form-label fs-md">`, not a `<label for="">`: an empty `for` names nothing and makes `pgInjectAriaAndRequiredMarkers()` skip the label (the key code form's Report radios).
- Same on the ad region form (Display Type, Transition Type) and the calendar event recurrence (`On` weekdays, `By` month type); ids, names and collapse targets stay as they were. Test: `tests/content_form_group_names_test.php`.

Test: `tests/offers_keycodes_names_test.php`.
