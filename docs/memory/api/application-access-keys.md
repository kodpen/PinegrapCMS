---
name: application-access-keys
description: Application Access (api_settings.php): rows made and secrets issued through includes/api/keys.php, 24 h grace, keyless rows refused, rows of a higher-ranked owner locked
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
