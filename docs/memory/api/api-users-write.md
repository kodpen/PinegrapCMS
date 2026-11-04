---
name: api-users-write
description: External API POST /users, POST /users/{id}, DELETE and POST /users/{id}/delete (users:write, administrator owners only): the bounds in the handler, the fields, what is never written, events
---

## Which routes and scope?
- `POST /users` (201), `POST /users/{id}` (also PATCH), `DELETE /users/{id}` and its twin `POST /users/{id}/delete`, all `dry_run`, in `includes/api/resources/users.php` on the screens' write functions (`core/user-write.md`). Scope `users:write`; `api_owner_scopes()` gives it to role 0 only, `users:read` stays at the designer ceiling (`api-users-read.md`). An assistant key never holds it (it takes only reads, plus Workspace and Tasks writes). PR #6574 (step X13b; plan issue #5237).
- Why not read-only: an HR or SSO sync must open and close staff accounts. Why not every role: a leaked key could make an administrator, which nothing on the site undoes in time. Running sites change only when an administrator gives a key `users:write`.

## Which bounds does the handler hold itself?
- `api_user_writer_or_fail()`: the owner's role is 0, checked again in the handler, not in the ceiling alone.
- `api_user_write_role()`: the role written is strictly below the owner's, so `administrator` is 422 on create and update (the enum lists it so the handler's bound answers).
- `api_user_row_writable()`: an administrator's row and the owner's own row are 403 on update and delete.
- `permissions` only for the role `user` (422 otherwise: the screen shows "every right" for the roles above), and only the rights the screen offers on this site (`api_user_permissions_offered()`, by the modules on); `page_types` replaces the stored list. Enum rights are stored `yes`/`no`, TINYINT ones `1`/`0`.
- `pg_user_write_problem()`: 422 for a blank username or bad address, 409 `username_taken` / `email_taken`.

## How is an update written?
- `api_user_fields_from_row()` reads the stored values (password hint included, written back but never answered), the sent fields are laid over them (`api_user_fields_apply()`), then `pg_user_update(..., false)`: folder and item access is not part of the API and stays as it is.
- `disabled` is the screen's Account enabled switch (`account_enabled_drawn`), with `pg_user_form_may_disable()`; before the column exists it is 503 `service_unavailable`.

## What is never written or sent?
- No password, hint, second factor or token on any route: a new account always gets the set password link mail (`pg_user_new_account_mail()`), a reset stays in the panel. Folder, menu, region, contact group, calendar and ad region access stays the panel's.
- Events `user.created`, `user.updated`, `user.deleted` through `pg_announce()` carry `id`, `username`, `email`, `role` only (`api_user_event_payload()`); the panel's own user screens do not announce. The site log line names the application and key, like the customer writes; the audit lines come from the write functions.

Tests: `tests/api_users_test.php` (role ceiling, own and administrator rows, the permission subset, the fields kept, the dry run before each write).
