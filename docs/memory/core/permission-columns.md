---
name: permission-columns
description: Adding a user right: column type and naming, the role-3 ladder files that must learn it, column probes, drawing it on the user screens, exact stored values, separate gates for a card and its links.
---

## What does a permission column look like?
- New rights are unprefixed `TINYINT UNSIGNED NOT NULL DEFAULT 0` columns on `user`, added by a migration step: `manage_erp`, `manage_erp_cash`, `manage_erp_settings`, `manage_workspace`, `manage_workspace_assign`... The inherited rights are `user_`-prefixed `ENUM('no','yes')` columns (`user_manage_calendars`). There is no None/Read/Write triple in this codebase.
- Roles 0-2 hold the module rights by role; the column decides for role 3 (the `manage_ecommerce` pattern, `access-control-and-roles.md`).

## Which files must learn about a new right?
- A right that on its own lets a role-3 user into the panel goes into every role-3 ladder, the checks that decide "may this person use the panel at all": `welcome.php` (the access refusal at the top), `get_page.php` (control panel access), `includes/fn/content.php`, `set_password.php` (where to send the user next) and `reset_password.php` (whether the mail names the panel login). Skip one and a user whose only right is the new one falls to "no access" there.
- The sign-in path reads such columns only behind a probe in `includes/authentication.php` (`pg_user_has_erp_columns()`, `pg_user_has_ws_columns()`, `pg_user_has_erp_readonly_column()`): between new files and the upgrade, a login query naming a missing column answered "Unknown column user.manage_erp" and locked the operator out of the screen that runs the upgrade. A new column of such a family goes into its probe or gets one.

## How is a right drawn and saved?
- Through `pg_user_permission_ui($config)`, `pg_user_role_cards()`, `pg_user_permission_everything()` (`includes/user_permissions.php`) on `add_user.php`, `edit_user.php`, `import_users.php` (`ui/user-permission-rows.md`).
- Keep the field name and its exact value: some switches post `'yes'` (`manage_calendars`), others `'1'` (`view_card_data`), and the POST handlers of all three screens compare the exact value.

## Can one right gate a card and its links?
- Gate them apart when they need different rights: the dashboard's sales report card is `USER_MANAGE_ECOMMERCE_REPORTS`, its links to orders need `manage_ecommerce`, and a user can hold one without the other; rows are then drawn without links (`ui/dashboard-sales-map-panel.md`).

Related: docs/memory/core/access-control-and-roles.md
