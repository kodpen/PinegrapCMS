---
name: dashboard-module-switches
description: The dashboard and the Users list follow the Site Settings module switches (CALENDARS, FORMS, ECOMMERCE) and the greeting's feature lines link only to screens the reader can open
---

## What is the rule?
- A module switched off in Site Settings › Features is left out of the dashboard and the Users list as the main menu (`includes/fn/output.php`) and the bell (`includes/notifications.php`) leave it out. A greeting line links only to a screen whose own gate admits the reader. The permission rules and the target screens' gates did not change.
- Symptom fixed: with Calendars off a manager still saw the Calendars card and its link to `calendars.php`; with Forms off the Activity card had a Forms tile and the greeting counted submissions; a role 3 user with only, say, `manage_contacts` was offered `add_product.php`, `view_folders.php` or `settings_security.php` and landed on "Access denied".

## Which places follow which gate?
- `welcome.php` widget 20 (Calendars): `CALENDARS === true` and `validate_calendars_access($user, true)`.
- `includes/dashboard/widgets/widget_7.php` Forms kind and the greeting's forms signal (`brief.php`): `FORMS === true` beside their role checks.
- Greeting feature lines (`pg_dashboard_brief()`): product, offer and product groups under `ECOMMERCE === true` and `validate_ecommerce_access()` (role < 3 or `manage_ecommerce`); file manager, pages and comments under `validate_area_access($user, 'user')` (role < 3 or `no_acl_check()`); the device-limit and settings lines under role <= 2 (`includes/settings/screen.php`). The deep database check line has no link and no gate, so the pool is never empty.
- Users list (`includes/fn/user_list.php`): the default view's Calendar Manager column needs `CALENDARS`, like Forms and Commerce beside it. `pg_user_list_filters($role, $modules = null)` drops My Calendar / Submitted Forms / Commerce Managers for a switched-off module; `null` reads the constants with `defined()` (undefined counts as on, for CLI tests). `pg_user_list_filter()` still accepts those names from the address.

## How is it tested?
- `tests/dashboard_module_gates_test.php`: source pins on each gate (a greeting link must sit inside an `if` block holding its screen's condition) and the filters with modules on and off.

Related: docs/memory/ui/main-menu-entry-gates.md, docs/memory/ui/dashboard-welcome-card.md, docs/memory/ui/link-rights.md
