---
name: settings-drawer-card-form
description: A settings drawer that edits a record (a department): fields and a named button joined to the settings form by its id, saved by the pane's Save
---

## How does a drawer edit a record without leaving the dialog?
- Its fields and its button carry `form="<settings form id>"`: `pg_settings_modal_form` in the dialog, `pg_settings_page_form` on the page path (`screen.php`). The drawer sits outside the form (`#pg_settings_modal_drawers`, or Bootstrap's drawer after the page form), so the attribute is what joins them. First user: a department's drawer, `ws_settings_department_drawer()` (PR #8721).
- The button is a named submit button (`ws_department_save` = the record id, 0 for a new one): the pane's save runs and the save module then does the job, as for any card button (`settings-card-own-button.md`). A plain Save sends the drawer's fields too and ignores them.
- Why not a form posting elsewhere: a full-page post leaves the dialog and drops unsaved pane changes, the risk the owner ruled the drawer in for (`workflow/decisions-made-panel-3.md`).

## What makes it work in the dialog's script?
- jQuery's `serialize()` reads `form.elements`, which holds form-associated controls outside the form, so the snapshot, the dirty mark and the save body include them.
- The click that remembers the card button also listens on `#pg_settings_modal_drawers [type="submit"][form="pg_settings_modal_form"]`; `markable()` and `unmark()` look in the drawers too, so a refused field is marked in the open drawer.

## What are the rules for such fields?
- Names carry the record id (`ws_dept[3][name]`), so every drawer's fields stay apart in the one form; the category's `lists` entry allows the depth (`'ws_dept' => 3`).
- No `required` (the pane's Save would be blocked by a drawer field); the server checks and marks it.
- Text fields are read-only until pressed (the panel's anti-autofill latch): a browser test clicks a field before typing.

Tests: `tests/settings_workspace_team_test.php`.
