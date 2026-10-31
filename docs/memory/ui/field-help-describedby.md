---
name: field-help-describedby
description: pgBindFieldHelp() in backend.src.js ties a panel field's .form-text help to the field with aria-describedby at runtime: which field, what is skipped, when it runs, what is left
---

## Why?
- Panel screens print a field's help as a visible `.form-text` beside it, but almost no screen passed `aria-describedby` (834 `form-text` in 167 panel PHP files, 56 `aria-describedby`). A screen reader user tabbing to the field heard its label only.

## Which field does a help belong to?
- The one `input`, `select` or `textarea` in the help's parent (hidden, submit, button, reset and image inputs and the chips `.form-ghost` do not count).
- Else, when the parent holds several, the one control of the help's previous element sibling, if it is not a radio: a switch in a `.form-field` with the help after it (`page_form.php` double opt-in, `edit_page.php` noindex).
- The help gets an id when it has none (`<field id>-help`, a counter suffix when taken; `pg-field-help` without a field id) and is added with `pgDescribedBy()`, other ids kept (an error link stays). A drawn combobox toggle (`el.pgComboboxToggle`) and chips typing box (`el.pgChipsBox .form-ghost`) get it too.

## What is left alone?
- Help bound by hand: a field whose `aria-describedby` names a `.form-text`, or a help whose id any `aria-describedby` already names (`#tr_auto_hint`, `#url`, `#ws_hol_to`).
- A help after a group of controls or a radio (`erp_settings.php` radio pair), a help inside a `label` (`prep.php`), an intro with no control (`edit_key_code.php` heading text).
- Date and time hook fields (`data-pg-date`, `-datetime`, `-time`, inside `data-pg-date-selects`): the date picker plan owns them.
- A field with `title` or `data-bs-original-title`: Bootstrap 6's Tooltip `hide()` removes `aria-describedby`, and `_fixTitle()` moves the title into `data-bs-original-title`, so `title` alone is not enough after `pgBindTitlePopovers()`.

## When does it run?
- On `DOMContentLoaded` after `pgInjectAriaAndRequiredMarkers()`, never inside it: `output_errors()` calls the injector mid-parse. `data-pg-help-bound` marks a help done, so a second run changes nothing.
- In `pgInitInjected(scope)` (Site Settings dialog, DataTables draws, product group form, designer page settings) before `pgFields.init`, so `pg_fields.js` copies the field's `aria-describedby` onto the combobox toggle it draws.

## What is not covered?
- A chips box drawn after the binder (page load: `pg_fields.js` draws later and copies no `aria-describedby` to its `.form-ghost`): the hidden input is linked, the typing box is not.
- Screens without `backend.min.js` (the legacy Page Designer).

Test: `tests/field_help_describedby_test.php` (Node fake document, wiring pins).
