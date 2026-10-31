---
name: user-shared-form
description: The user create and edit screens share includes/forms/user_form.php (rights cards, folder rights, access save)
---

## Where are the shared user account parts?
- `includes/forms/user_form.php` (gate `PG_FUNCTIONS_DIR`). `add_user.php` and `edit_user.php` stay at the root and `require_once` it beside `includes/user_permissions.php` (the permission rows and panels, see `user-permission-rows.md`).
- `pg_user_form_rights_cards($rows)` prints the full-access card and the permission rows card.
- `pg_user_form_folder_rights($post, $folder_id)` returns `rights` (2 edit, 1 view, 0 none) and `expiration_date` (an invalid date gives 0).
- `pg_user_form_save_access($user_id, $role, $post, $replace)` writes `aclfolder` for every folder and, for role 3, the common region, shared component, menu, contact group (with manage contacts or e-mails), calendar (with manage calendars) and ad region (when `ADS`) grants.

## What does `$replace` change?
- Edit (`true`) deletes the old rows first. Common regions made shared components are left out of both the delete and the list, so their grant stays for when the component is deleted. Create (`false`) lists every common region.
- Edit keeps the old item grants when the role changes away from 3, as before: they are only rewritten for role 3.

## What stays in the screens?
- Create: account card, validation, the user INSERT, notify mail, password notice. Edit: profile layout, sessions, sign-in and contact cards, the user UPDATE, audit.

## Why this pair?
- PR #2507. Measured: add 743 and edit 1,884 lines, about 432 shared, the largest one no open PR touched. Tests: `tests/user_form_test.php`.

## What does Edit User's save write while a module is off?
- Only the columns of rows the screen drew. `pg_user_form_module_columns($drawn)` (in `user_form.php`, tested in `tests/user_form_test.php`) maps PASSWORD_HINT, FORMS, CALENDARS, ECOMMERCE (offline payment only with ECOMMERCE and ECOMMERCE_OFFLINE_PAYMENT) and ERP_ENABLED to their columns; `edit_user.php` builds `$sql_module_rights` from it, and writes `$sql_erp_readonly` only with ERP_ENABLED and `$sql_ws_rights` only with `pg_user_permission_ws_available()`. The calendar grants (`users_calendars_xref`) are deleted and rewritten only while CALENDARS is on, like the ad grants with ADS.
- Why: an off module posts nothing, and the save stored '' or 0, so saving a staff member (e.g. to change the e-mail) silently took the rights away and gave "Access denied" once the module was back. The password hint was blanked the same way while hints were off.
