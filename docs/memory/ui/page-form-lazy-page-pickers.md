---
name: page-form-lazy-page-pickers
description: Create Page / Edit Page untyped page pickers carry only their stored page and fill from one <template> when opened (select_page(), pg_page_picker_lazy()); the page list is read once per request
module: forms
---

## What was wrong?
- `pg_page_form_type_fields()` prints every page type's fields, hidden until the type is chosen. About 21 untyped `select_page()` pickers each listed every page and each ran its own query: on a site with ~300 pages Create Page sent ~680 KB of HTML and ~10,000 DOM nodes (#6077).

## How does it work now?
- `add_page.php` and `edit_page.php` pass `$lazy_pickers = true` to `pg_page_form_type_fields()`, which switches `pg_page_picker_lazy()` on while it draws.
- While on, an untyped `select_page()` prints `pg_page_select_lazy_html()`: a hidden, disabled `<option data-pg-page-fill>` marker plus only the stored (selected) page, so an unopened picker still submits the stored value.
- `pg_page_picker_template()` is appended once to the properties markup: a `<template id="pg_page_picker_options">` with every page and an inline script. On `focusin`, `mousedown` or `touchstart` of a marked picker it removes the marker, appends the list and keeps the value. A stored page the list has no option for (archived or in the bin) keeps its own marked option.
- Typed pickers (`select_page($id, 'catalog detail')`) are short and still print their options.
- The designer's page type pane (`page_type_pane.php`) draws one type and leaves the switch off: its HTML goes into the pane, where an inline script is not relied on.

## Where is the list read?
- `pg_select_page_rows($page_type)` reads the pages once per request per type list, archived folders included; `pg_select_page_options()` drops archived pages except the stored one through `pg_page_option_status()`, and marks it.
