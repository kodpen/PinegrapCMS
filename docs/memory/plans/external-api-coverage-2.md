---
name: external-api-coverage-2
description: External API plan, part 2: the users write step X13 (#5237) and why settings, firewall, sessions, backups and updates get no endpoint (#5238); claim as [plan:external-api-coverage] X13
---

## Which step is here?
- X13 Users write (#5237): `free` once X4 of `external-api-coverage.md` has landed.
  - Routes `POST /users`, `POST /users/{id}`, `POST /users/{id}/delete` (POST twins, `Idempotency-Key` on create, `api_dry_run_stop()` above the first write) through one write function moved out of `add_user.php` / `edit_user.php` / `delete_users.php` into an `includes/fn/` module that the screens and the API both call, so the screens' rules hold (unique e-mail, the role list, the permission columns, the delete as the screen does it).
  - Scope `users:write` in `api_scope_groups()`; `api_owner_scopes()` gives it only to an owner with role 0. `users:read` stays at the designer ceiling (X4).
  - Bounds in the handler, not in the ceiling alone: the written role is strictly below the owner's (an administrator writes 1–3, never 0); a row with role 0 and the owner's own row are refused (403; `role=0` on create 422); `disabled` as the screen's switch; permission columns as the screen offers them for the written role (`core/permission-columns.md`); no password field on any route: a new account gets the single-use set-password link mail (`pg_new_user_email_body()`), a reset stays in the panel.
  - Events `user.created`, `user.updated`, `user.deleted` through `pg_announce()`; the site log line the screen writes stays (`core/audit-trail-writers.md`). No payload carries a hash, an MFA secret or a token.
  - Tests `tests/api_users_test.php`: the role ceiling, the own row, an administrator row, a `role=0` create, the permission subset, the dry run.
  - Why not read-only: an HR or SSO sync could not open and close staff accounts. Why not full writes: a leaked key could create an administrator, which nothing on the site undoes in time. Running sites: nothing changes until an administrator gives a key `users:write`.

## Why do the settings screens get no endpoint? (#5238)
- Settings, firewall, sessions, backups and updates stay panel-only. What an integration needs from the settings is already read: `GET /meta` (currency, tax, timezone, the modules switched on, firewall mode, security headers, jobs, upload limits), `GET /system/status` for health, `GET /site-log` and `GET /audit-log` for who did what. Every write on those screens hangs on the screen's own guards (sealed secrets, the self-ban guard, files on the server), and a leaked key holding such a scope opens the whole site; a firewall write over the API can lock the owner out. A read-only settings tree would need a per-field secret check across sixteen screens, and no client has asked for it.
- Two doors stay open without a new decision: a single setting a client must read is added as a field of `/meta` under `meta:read` as free work; a real need for a settings group read or written (the fields named, the guard that holds) is a new architect issue, not a step claimed from this plan.
