---
name: form-item-view-layout
description: edit_form_item_view.php in the wide layout (B11) through includes/forms/form_item_view_form.php: layout card, sticky Summary and variables, save bar; what stays in the screen
---

## What does the screen look like?
- Wide layout (`edit-screen-wide-layout.md`): the form column holds the Layout card (code editor `layout`, the collection field marker in its hint); the status column holds a sticky box with the Summary (page, custom form or a muted None, collection A/B being edited, the page's last change with its user or a dash) and the Available variables card; the save bar is sticky under both (PR #6761).
- From `lg` the box (`#pg_fiv_status`, a scoped `@layer custom` rule) sticks and has `max-height: calc(100vh - 4.3rem)` with its own scroll: the variable lists are taller than a window, and without it their bottom (formats) could not be reached while the box stuck. Below `lg` it is plain flow: the cap cut the lists off on a phone.
- The "Displays submitted forms from" line above the form went into the Summary's Custom Form row.

## What is in the shared file and what stays in the screen?
- `includes/forms/form_item_view_form.php`: `pg_form_item_view_form_card()`, `_summary()`, `_status_row()`, `_variable_items()`, `_variables()` (the lists built from `pg_cf_variables_help()`, `form-item-view-variables.md`), `_status()`, `_layout()`. Tests: `tests/form_item_view_form_test.php`; `tests/phone_width_strips_2_test.php` reads the variables header there.
- The screen keeps the gates, the page and collection load, the Hints dialog, the click-to-insert script (it finds `#pg_fiv_variables`), and the save.
