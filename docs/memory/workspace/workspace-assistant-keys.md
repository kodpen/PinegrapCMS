---
name: workspace-assistant-keys
description: "Create Claude's key" and "Reset secret" on the Claude card: the key made, its scopes (five reads left out), the config link, who sees it, no-grace reset, one-time window
---

## What does "Create Claude's key" make?
- `ws_claude_key_post()` (`includes/workspace/claude.php`) calls `api_app_create()` (`includes/api/keys.php`): an `api_apps` row named Claude, kind `server` (never `device` or `oauth`, which let people sign in), status active, rate 120 a minute, owner and creator = the administrator who pressed it, a secret stored only as `api_secret_hash()` with its 4-character hint. Audit line `create` (api_* fields masked by name), Site Log "API application (Claude) was created."
- The id goes into `config.ws_claude_app_id`, with `ws_claude_error` and `ws_claude_hold_until` cleared. No schema change.
- Offered only while `ws_claude_app()` finds no row: no key chosen, or the linked one deleted in Application Access (nothing sets `revoked`). A key linked by hand is kept; only its secret can be reset.

## Which scopes does the key get?
- `api_assistant_scopes(api_scope_groups(), api_assistant_scopes_left_out())` (`includes/api/scopes.php`, pure, tests/api_assistant_scopes_test.php): one choice per group, the read of every group, but `workspace:write` and `tasks:write` for those two. No other write, never `webhooks:manage`.
- Cut to `api_owner_scopes($user)` of the person pressing it; refused when the two Workspace writes are not left. Every call is capped again by `api_effective_scopes()`, so a demoted owner narrows the key at once.
- Left out (owner decision 2026-10-24): `account:read`, `key_codes:read`, `gift_cards:read`, `site_log:read`, `system:read`. Claude reads records with the owner's rights, so any asker in a channel could get them.
- Not added (owner decision 2026-10-24): `design:write` (page proposals, the Visual Page Editor's assistant) and `translations:write` (the job queue): both also write live. An administrator adds them in Application Access.

## Who may press the buttons, and who sees the key?
- Role 0 only, for both actions (POST, `validate_token_field()` in workspace_settings.php). Role-3 holders of the settings right see the card but no key, no prompt block, no buttons.
- The administrator sees the key, `Secret: ••••` plus the hint, and the prompt, all copyable. The secret is never printed again.

## What does "Reset secret" do?
- `api_app_rotate_secret($id, 0, $username)`: the id comes only from `ws_claude_app()` (the config link), never from the post; kind other than `server` is refused. Audit line `rotate`, Site Log line.
- Grace 0: the old secret, and a previous one still inside an Application Access rotation's 24 h, stop at once (`api_secret_matches()`). The confirm warns that anything else using a hand-linked key stops too. Application Access keeps its 24 h rotation.

## How is the secret shown only once?
- Handed to the next request in `$_SESSION['software']['ws_assistant_credentials']` (agent, name, key, secret, rotated), never the address or a form field. workspace_settings.php reads it once, unsets it at once, sends `Cache-Control: no-store` and passes it to the card.
- The card draws a static `modal fade show` window (no Bootstrap modal JS, as api_settings.php): key, secret, domain, prompt, where they go on claude.ai. "I have copied it…" unlocks Close, which removes the window from the DOM. Field ids `ws-claude-sheet-*`, `ws-claude-key`, `ws-claude-key-prompt`; the guide keeps `ws-claude-prompt` and `ws-claude-host`.
- If that GET never comes, it stays in the session until the session ends (as api_settings.php).

## What must stay so, and what was never tried?
- The application select stays inside the Save form ("Use another application"): outside it, a Save posts 0 and unlinks the key.
- Never tried on a live routine: signing in with a new key, the old secret failing right after a reset.
