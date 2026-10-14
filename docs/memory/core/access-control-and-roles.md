---
name: access-control-and-roles
description: validate_area_access() gates, real role numbering (Designer above Manager), role table, job-script gating, and the ecommerce role rule.
---

## What do the validate_area_access() gates mean?
- `validate_area_access($user, 'administrator')`: only `USER_ROLE = 0` (admin).
- `'designer'`: designer + admin (role <= 1). `'manager'`: role <= 2. `'user'`: role 3.
- Short-link creation (`add_short_link.php`, File Manager `explorer_short_link_create` / `explorer_short_link_duplicate`) is behind the `'manager'` gate. Edit/delete (`edit_short_link.php`) stays open to role 3.
- Every page starts with `validate_user()` to authenticate the user.

## Do job scripts go through the gate?
- Yes. `update_exchange_rates.php` and `waf_ranges_job.php` use `pg_cron_is_background_run()`: only CLI or `PG_CRON_DISPATCH` (the job.php dispatcher defines it right before the include) passes; every other request goes through the gate of the panel screen that offers the button.
- Never skip the user check by looking at whether `send_to` exists. A new job script uses the same pattern.

## What are the roles?
| Role | Value | Can access | Cannot |
|---|---|---|---|
| Administrator | 0 | Everything | none |
| Designer | 1 | All non-admin areas including design tools | admin-only areas (`'administrator'` = role 0) |
| Manager | 2 | Content, ecommerce, settings | Design tools (`'designer'` areas need role <= 1) |
| User | 3 | Basic content | Ecommerce, visitors etc. need a special flag |

## Is Designer above or below Manager?
- Designer (1) is ABOVE Manager (2). Verified in code: `select_user_role()` builds the dropdown 0=Administrator, 1=Designer, 2=Manager, 3=User; `validate_area_access()` maps `'designer'`->1, `'manager'`->2, `'user'`->3; `view_users.php` filters `all_site_designers` -> role<2, `all_site_managers` -> role<3. (An old table said 1=Manager, 2=Designer, 3=Contributor; that was wrong.)

## What is the ecommerce (ECOMMERCE) permission pattern?
- Roles 0-2 are EXEMPT from the `manage_ecommerce` flag. Role 3 requires `manage_ecommerce = true`.
