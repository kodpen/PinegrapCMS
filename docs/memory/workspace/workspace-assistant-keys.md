---
name: workspace-assistant-keys
description: The Create buttons on the Claude and Pinegrap AI cards and Claude's "Reset secret": the row made, scopes, config link, who sees it, one-time window, a key that cannot sign in
---

## What does "Create Claude's key" make?
- `ws_claude_key_post()` (`includes/workspace/claude.php`) calls `api_app_create()` (`includes/api/keys.php`): an `api_apps` row named Claude, kind `server` (`device`/`oauth` let people sign in), active, 120/min, owner and creator = the presser, the secret kept only as `api_secret_hash()` and a 4-character hint. Audit line `create` (api_* masked), Site Log "API application (Claude) was created."
- Linked in `config.ws_claude_app_id` (error and hold cleared). No schema change.

## What does "Create Pinegrap AI's key" make?
- `ws_ai_key_post()` (`includes/workspace/ai.php`, action `ai_key_create`): the same row named Pinegrap AI without a secret (hash and hint empty), linked in `config.ws_ai_app_id`; same audit and log lines. No window, no reset (owner answer 3a).
- The row is only an identity (`<@app:N>`), a read filter (`ws_ai_readable_types()`) and a poster (`ws_ai_owner()`).
- It cannot sign in: key + secret need a stored hash (`api_secret_matches()` → `api_secret_hash_accepts()` refuses an empty one); bearer tokens (devices, OAuth, MCP) live only under `device`/`oauth` rows. `api_app_can_sign_in()` (pure, tests/api_app_sign_in_test.php) draws the card's line.
- An administrator can still give it a secret by hand (Application Access, or linked to Claude and reset); the line then says so.

## When is Create offered?
- Only while `ws_claude_app()` / `ws_ai_app()` finds no row: none chosen, or the linked one deleted in Application Access (nothing sets `revoked`). A key linked by hand is kept.

## Which scopes does the key get?
- `ws_assistant_key_scopes($user)` for both: `api_assistant_scopes(api_scope_groups(), api_assistant_scopes_left_out())` (pure, tests/api_assistant_scopes_test.php), per group its read, `workspace:write` and `tasks:write` for those two, no other write, never `webhooks:manage`; `api_assistant_scopes_handed_on()` cuts it to the presser's `api_owner_scopes()`, empty (refused) without both writes. `api_effective_scopes()` caps every call again.
- Left out (owner, 2026-10-24): `account:read`, `key_codes:read`, `gift_cards:read`, `site_log:read`, `system:read`: Claude reads as the owner, so any channel asker would get them.
- Not added (owner, 2026-10-24): `design:write`, `translations:write` (live writes); Application Access adds them.

## Who may press the buttons, and who sees the key?
- Role 0 only (POST, `validate_token_field()` in workspace_settings.php). Role-3 settings holders see the cards without key, prompt or buttons.
- Claude: key, `Secret: ••••` + hint and prompt, copyable. Pinegrap AI: name, id and whether it can sign in.

## What does "Reset secret" do?
- Claude only: `api_app_rotate_secret($id, 0, $username)`, id from `ws_claude_app()`, never the post; non-`server` refused. Audit `rotate`, Site Log line.
- Grace 0: the old secret and one still in an Application Access rotation's 24 h stop at once; the confirm warns other users of a hand-linked key.

## How is Claude's secret shown only once?
- `$_SESSION['software']['ws_assistant_credentials']` (agent, name, key, secret, rotated) to the next request, never the address or a field; workspace_settings.php reads and unsets it, sends `Cache-Control: no-store`.
- A static `modal fade show` window (no Bootstrap modal JS): key, secret, domain, prompt; "I have copied it…" unlocks Close, which removes it. Ids `ws-claude-sheet-*`, `ws-claude-key`, `ws-claude-key-prompt`; the guide keeps `ws-claude-prompt`, `ws-claude-host`. Unfetched, it lasts until the session ends.

## What must stay so, and what was never tried?
- Both selects stay inside the Save form ("Use another application"): outside it, a Save posts 0 and unlinks the key.
- Never tried live: signing in with a new key, the old secret failing after a reset, Pinegrap AI answering through a key made here.
