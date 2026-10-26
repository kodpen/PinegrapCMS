---
name: application-access-keys
description: How Application Access (api_settings.php) makes an application and issues a new secret: through the helpers of includes/api/keys.php, 24 h grace, keyless rows refused
---

## Where is an api_apps row made?
- Only in `api_app_create()` (`includes/api/keys.php`): Application Access's "New application" and the Workspace assistants' cards (`workspace/workspace-assistant-keys.md`). No screen writes its own INSERT (tests/api_app_keys_test.php).
- The posted kind goes through `api_app_kind()`: `device`, `oauth`, else `server`. `api_app_issues_secret()`: only a `server` row asked for one gets a secret; device and OAuth rows never do.
- The plain secret lives only in the answer; Application Access hands it to the next request in `$_SESSION['software']['api_new_credentials']` and shows it once. A failed INSERT shows "The application could not be created." instead of a sheet for a row that does not exist.

## How is a new secret issued?
- Only by `api_app_rotate_secret($id, $grace, $username)`. Application Access passes 86400 (the old secret answers a day), Claude's Reset 0.
- A row without a secret (Pinegrap AI's) is refused: the screen asks `api_app_secret_can_rotate()` first for its message, and the helper asks it again and keeps such a row out of its UPDATE (`api_secret_hash <> ''`). If the UPDATE matches nothing the secret is not shown ("That application could not be found.").

## What is still open?
- Application Access (roles 0-2) rotates and re-scopes every row, an administrator's too: a Manager can rotate Claude's key and use it with the admin's rights as the cap. Refusing rows whose owner outranks the editor is an open owner question (plans/workspace-assistant-key-setup.md, question 6); today only rows without a secret are refused.
