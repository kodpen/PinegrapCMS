---
name: user-permission-rows
description: Permission rows in add_user/edit_user/import_users (includes/user_permissions.php): value rules, gate_field/gate_scope, panels, z-index, multiselect pitfalls.
---

## How are permission sections drawn?
- `add_user.php`, `edit_user.php` and `import_users.php` draw the permission section from one place, `includes/user_permissions.php`: `pg_user_permission_ui($config)` (rows and panels), `pg_user_role_cards()` (role cards), `pg_user_permission_everything()` (the card shown for full-access roles).
- A screen supplies only the selection markup (`$config['panels']`): the add screen calls the generators empty, the edit screen with the user's selections.
- Classes are in `backend.src.css`, end of file, block *Admin screen — user permission rows*: `.pg-perm*`, `.pg-role*`, `.pg-identity*`, `.pg-fact*`, `.pg-session*`.

## What four rules apply?
- Field name and value never change. Some columns store `'yes'`, others `'1'` (`manage_calendars` = `yes`, `view_card_data` = `1`); the POST handlers of all three screens check these exact values. Pass the value to `pg_user_permission_switch()` as is.
- The gate says what it drives, by name. A group with its own column: the switch is that column (`gate_field`). A group without: the switch is a UI gate and what it clears is written with `gate_scope` (a list) or `gate_fields` (fields in the panel). Do not infer from counters: page types are counted on the content row, but access is given by the folder list.
- Counts are read from the boxes, not the server. `backend.src.js` writes the row badges from the panel's `input.multiselect-checkbox` state; the `counts` passed from PHP only decide the first gate state. Badge and submitted data cannot drift.
- Panels stay inside the form. Bootstrap offcanvas moves nothing in the DOM, so fields in a panel inside `<form>` submit normally. Things with their own form (sign out sessions, disconnect Google) MUST stay OUTSIDE the main form (forms cannot nest): in `edit_user.php` they are in a separate panel after the main form closes.

## Which pitfalls exist?
- `multiselectCheckbox` does not fire an event per box. "Select all" writes each box's `.checked` programmatically and fires a single `change` only for ITS OWN switch. Behaviour bound to a checkbox's `onclick` never runs on select-all. Bind behaviour to STATE, not events, and re-sync everything on any change in the list (examples: `pgSyncAclExpiry()`, `pgSyncCheckboxList()`). For the same reason the third (`indeterminate`) state of the select-all switch is computed by this code, not the plugin.
- Chat bubble has `z-index: 1080`, above the offcanvas (1045), so it is hidden with `body.pg-side-panel-open` while a panel is open; raising the panel would leave the bubble above the backdrop.
- jQuery UI datepicker inside a panel: force `z-index`. The calendar hangs off `<body>` and computes its `z-index` from the field's ancestors, inline; inside an offcanvas (1045) or modal (1055) that value stays low and it draws BEHIND the panel. `#ui-datepicker-div` is pinned to 1065 with `!important` in `backend.src.css`.
- `db_items()` returns an ARRAY, not `mysqli_result`: never call `mysqli_fetch_assoc()` on it, use `foreach`. It stayed hidden in the session list for long: an empty result is an empty array and hits the `if ($rows)` guard; it only breaks fatally when rows exist.
