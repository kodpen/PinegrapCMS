---
name: workspace-settings-row-names
description: Workspace Settings rows name their person or department, hours boxes use the panel's decimal separator, the Claude guide's copy fields are named (#2918)
---

## How are the row controls of Workspace Settings named?
- Every per-person field (Team members: Job title, Hours a day, Working days group, Not in the team; department form: Team lead, Own department) has `aria-label` `'{var:1}: {var:2}'` with the field's label and the person's name: the first cell is a `<td>`, so without it a screen reader hears the same name on every row.
- `ws_settings_days_html()` takes an optional fourth `$label`; empty means "Working days" (the Defaults card).
- A department row's Edit button reads "Edit: <department>" and its icon-only archive button "Move to the archive: <department>" / "Bring it back: <department>".

## Which decimal separator do the hours boxes use?
- `ws_settings_hours()` writes `pg_number_separators()['decimal']` ("7,5" on a Turkish panel); `ws_settings_minutes()` reads both `,` and `.`, so saving is unchanged.

## How do the Claude guide's copy fields get a name?
- The `$copy` closure in `includes/workspace/claude.php` takes an optional fourth `$label` that becomes the field's `aria-label`, for a field with no `<label for>` beside it: the guide's routine prompt and domain (`claude_setup.php`).

Test: `tests/workspace_settings_names_test.php`.
