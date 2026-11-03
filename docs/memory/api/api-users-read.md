---
name: api-users-read
description: External API GET /users and /users/{id} (users:read, roles 0-1): the users screen row rule, the User object, columns never read, two-step state
---

## Which routes and scope?
- `GET /users`, `GET /users/{id}` (`includes/api/resources/users.php`), scope `users:read` in `api_scope_groups()` with no write half yet (the writes are step X13 of `plans/external-api-coverage-2.md`). `api_owner_scopes()` gives it to roles 0-1 only, although `view_users.php` opens to managers: the list of every account with its rights and second factor is the map of who reaches what (PR #PRNUM).
- `api_assistant_scopes_left_out()` leaves `users:read` out of an assistant's key, like `site_log:read` and `system:read`; an administrator can add it in Application Access.

## Which rows does an owner reach?
- As on `view_users.php` (`pg_user_list_view()`): an administrator every account, anyone else `user.user_role > <own role>` (`api_user_owner_filter()`); a designer's application gets 404 for an administrator, another designer and itself.

## What does a User carry?
- `id`, `username`, `email`, `role` (`administrator|designer|manager|user`, `api_user_roles()`), `customer_id` (`user_contact`), `start_page_id` (`user_home`), `timezone`, `permissions` (`UserPermissions`), `two_step` (`UserTwoStep`), `google_linked`, `last_online_at` (`user_online_timestamp`), `created_at` (`user_created`, null for accounts made before the stamp), `updated_at` (`user_timestamp`).
- `permissions` is what is stored: the `user_` ENUM rights read `'yes'`, the TINYINT ones `1` (`api_user_permission_columns()`), plus `page_types` from the `user_set_page_type_*` columns. Roles 0-2 hold the module rights by role whatever is stored (`core/permission-columns.md`).
- The ERP and Workspace columns and `user_created`/`user_google_id` are named in the SELECT only behind their probes (`pg_user_has_erp_columns()`, `pg_user_has_ws_columns()`, `pg_user_has_erp_readonly_column()`, `pg_auth_column_exists()`); a missing one reads false/null.

## What is never read?
- The SELECT names its columns (`api_users_select_sql()`, never `user.*`): no `user_password`, hint, `token`, `user_devpasspin`, `secret_key*`, nor the `user_mfa` secrets; Google only as `google_linked`. `tests/api_users_test.php` checks the SQL and the field names.

## How is two_step worked out?
- Two queries per page (`api_users_two_step()`): `user_mfa` rows with `enabled_at > 0` and a key (as `pg_mfa_enabled()`), passkey counts from `user_passkeys`, each behind its table probe. `required` is role <= `MFA_REQUIRED_ROLE` while `pg_mfa_available()`, as `pg_mfa_required_for_user()`. The pure part is `api_user_two_step_state()`.

## Why no updated_since?
- `user_timestamp` does not move on a sign-in or a second-factor change, so the list pages by id (`api-endpoint-rules.md`).
