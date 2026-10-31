---
name: application-access-keys
description: Application Access (api_settings.php): rows made and secrets issued through includes/api/keys.php, 24 h grace, keyless rows refused, rows of a higher-ranked owner locked, one OAuth app, per-app logs
---

## Where is an api_apps row made?
- Only in `api_app_create()` (`includes/api/keys.php`): Application Access's "New application" and the Workspace assistants' cards (`workspace/workspace-assistant-keys.md`). No screen writes its own INSERT (tests/api_app_keys_test.php).
- The posted kind goes through `api_app_kind()`: `device`, `oauth`, else `server`. `api_app_issues_secret()`: only a `server` row asked for one gets a secret; device and OAuth rows never do.
- The plain secret lives only in the answer; Application Access hands it to the next request in `$_SESSION['software']['api_new_credentials']` and shows it once. A failed INSERT shows "The application could not be created." instead of a sheet for a row that does not exist.

## How is a new secret issued?
- Only by `api_app_rotate_secret($id, $grace, $username)`. Application Access passes 86400 (the old secret answers a day), Claude's Reset 0.
- A row without a secret (Pinegrap AI's) is refused: the screen asks `api_app_secret_can_rotate()` first for its message, and the helper asks it again and keeps such a row out of its UPDATE (`api_secret_hash <> ''`). If the UPDATE matches nothing the secret is not shown ("That application could not be found.").

## Who may change a row?
- An editor whose role does not rank below the row owner's (`api_app_row_locked($owner_role, $editor_role)`, lower number outranks: administrator 0, Designer 1, Manager 2; equal rank allowed). Else a Manager could rotate Claude's key and call the API with the admin's rights as the cap (owner decision 2026-10-28).
- `api_settings.php` checks it once before the action branches: every POST naming an `app_id` (update, rotate, delete, webhook_status/retry/delete, device_revoke) is refused with an error. The drawer gets `locked` and hides Save, new secret, delete and webhook/device buttons; the webhook card drops its buttons for such rows.
- A row whose owner was deleted (LEFT JOIN gives NULL) is not locked: its key answers `owner_unavailable`, so it carries no rights and any editor may clear it.
- Tests: tests/api_app_row_rank_test.php.

## Why can only one OAuth app be made?
- The site serves one, the oldest active (`pg_oauth_app()`); a newer row's scopes would reach no connection. `create` with kind `oauth` is refused while one is active ("This site already has an OAuth app: <name>; change its scopes there.", `api_app_oauth_create_refusal()`), before `api_app_create()`.
- A row that still exists (older data, or a disabled older app switched back on) is shown as "Not in use" in the list, its sub-line and a drawer warning (`api_app_oauth_served_id()`, `api_app_oauth_unused()`, `includes/api/app_access.php`). PR #4188, issue #4021.

## How does the Logs tab pick its rows?
- One `UNION ALL` query of bounded subqueries per application (`api_app_log_sql()`): its last 15 requests and last 15 errors, read on `idx_app_time (app_id, timestamp)`. Never a site-wide window: a busy app emptied the others' lists. "Errors only" shows the errors list. Today's rows show the time, older ones the date too (`api_app_log_time_label()`). PR #4188, issue #4023.
- Tests: tests/api_app_access_test.php.
