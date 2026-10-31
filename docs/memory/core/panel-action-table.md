---
name: panel-action-table
description: Adding an api.php panel action: the pg_panel_actions() row and its columns, handler file and signature, what the general gate does with listed, unlisted and prefixed names, the test.
---

## Where does a new panel AJAX action go?
- A function `pg_panel_<action>($request, $action)` in a group file `includes/panel/<group>.php` (gate `PG_PANEL_ACTIONS`), plus a row in `pg_panel_actions()` (`includes/panel/actions.php`): `file`, `handler`, `exempt`, `token`, `write`, and when needed `any_role`, `token_by`, `public`. The column meanings are in the header of `actions.php`.
- `api.php` has no `switch`, so no `case` is written there: it asks `pg_panel_gate()`, then `pg_panel_dispatch()` loads the file and calls the handler. The `chat_*`, `site_chat_*` and `ws_*` families share one handler each.
- A handler answers itself (`respond()` exits), returns an array the dispatcher `respond()`s, or returns nothing (an empty 200). The working directory is still `api.php`'s.
- A handler that loads the session user writes `global $user;` first: helpers it reaches (`check_folder_access_in_array()`, `select_folder()`, `get_page_options()`, the page and widget renderers) read `global $user`, and with a local variable their access checks fail closed.

## What does the general gate do?
- Listed with `exempt => true`: no general check; the handler checks session, token and role itself, in its own order (that order decides the error text clients rely on).
- Listed, not exempt: a session (`panel_login_refusal()`: the translated "Your session has expired. Reload the page and sign in again." with `code: session`, or "Invalid login." when the request sent a user name and password), then role <= 1 ("The User must have a Designer or Administrator role.", translated); `any_role => true` asks only for the session.
- An exempt handler that finds no session answers `respond(panel_login_refusal());` too, never its own literal.
- Not listed: the same session + role <= 1 gate, then `Invalid action.`. Only names starting with `chat_`, `site_chat_` or `ws_` (`pg_panel_exempt_prefixes()`) skip the gate and get `Invalid action.` directly.
- So an action open to role 2 or 3 is either `exempt => true` AND checks session, token and role in its handler, or `any_role => true` (the gate checks the session, the handler the rest; `get_form`, `get_forms`). An allowlist inside a handler never sees a request the gate stopped.

## What else must be right?
- Flags match the handler: `token` = it calls `validate_token()` (or `token_by` names the function that checks the token for it), `write` = it changes something, `public` = a storefront endpoint with no session and no form token.
- `tests/panel_actions_test.php` reads each handler's source against its flags and compares the exempt names with a fixed list kept in the test: making an action exempt is a deliberate change in both places.
- The body is JSON (`php://input`). A FormData post decodes to nothing: a designer gets `Invalid action.`, a lower role is stopped at the gate. Multipart needs its own script (`json-endpoint-rules.md`).

Related: docs/memory/core/api-php-role-gate-and-actions.md
