---
name: access-control-and-roles
description: validate_area_access() gates, real role numbering (Designer above Manager), role table, job-script gating, and the ecommerce role rule (panel and external API owner).
---

## What do the validate_area_access() gates mean?
- `validate_area_access($user, 'administrator')`: only `USER_ROLE = 0` (admin).
- `'designer'`: designer + admin (role <= 1). `'manager'`: role <= 2. `'user'`: every role (<= 3).
- A role-3 user passes no gate without edit rights to at least one folder (`no_acl_check()`: an `aclfolder` row with `aclfolder_rights = '2'`); for role 3 only `'user'` can pass.
- Short links are a right of roles 0-2: `view_short_links.php`, `add_short_link.php`, `edit_short_link.php` and `import_short_links.php` sit behind `'manager'`, and `pg_explorer_handle()` refuses every `explorer_short_link_*` action to role 3 (short links are outside the folder rights).
- Every page starts with `validate_user()` to authenticate the user, then its area gate. A button that is not drawn is not an access check.
- `validate_area_access()` answers with an HTML page; an endpoint that speaks JSON refuses in JSON (`json-endpoint-rules.md`).

## Do job scripts go through the gate?
- Yes. The job scripts (`*_job.php`, `update_exchange_rates.php`, `auto_backup.php`) use `pg_cron_is_background_run()` (`includes/fn/cron.php`): only CLI or `PG_CRON_DISPATCH` (the job.php dispatcher defines it right before the include) passes; every other request goes through the gate of the panel screen that offers the button.
- Never skip the user check by looking at whether `send_to` exists. A new job script uses the same pattern.

## What are the roles?
| Role | Value | Can access | Cannot |
|---|---|---|---|
| Administrator | 0 | Everything | none |
| Designer | 1 | All non-admin areas including design tools | admin-only areas (`'administrator'` = role 0) |
| Manager | 2 | Content, ecommerce, settings | Design tools (`'designer'` areas need role <= 1) |
| User | 3 | Basic content | Ecommerce, visitors etc. need a special flag |

- In the visual designer roles 2 and 3 work at content level (text, image addresses, marked areas; `pg_designer_access()` in `includes/designer_access.php`): designer/designer-content-roles-save-gate.md, designer/designer-content-roles-translations.md.

## Is Designer above or below Manager?
- Designer (1) is ABOVE Manager (2). Verified in code: `select_user_role()` builds the dropdown 0=Administrator, 1=Designer, 2=Manager, 3=User; `validate_area_access()` maps `'designer'`->1, `'manager'`->2, `'user'`->3; `view_users.php` filters `all_site_designers` -> role<2, `all_site_managers` -> role<3. (An old table said 1=Manager, 2=Designer, 3=Contributor; that was wrong.)

## What is the ecommerce (ECOMMERCE) permission pattern?
- Roles 0-2 are EXEMPT from the `manage_ecommerce` flag. Role 3 requires `manage_ecommerce = true`.
- The external API's owner ceiling follows the same pattern: what an application may do is capped by what its owner could do in the panel (`api_owner_scopes()`, `api/external-api-authorization.md`).
- Adding a right of your own: `permission-columns.md`.
