---
name: workspace-app-commands
description: An application's own / commands (ws_app_commands, 11.0.87, PR #2213): register/delete routes, workspace:commands, never posted, workspace.app.command to that app alone
---

## What is it?
- An application registers its own `/summarize`, `/report` with `POST /workspace/commands` (name, description, usage; 201 new, 200 changed) and removes it with `DELETE /workspace/commands/{name}` or `POST …/{name}/delete`. `GET /workspace/commands` lists them after the built-in ones (`app_id`, `app`; key `app:<name>`). Code: `includes/workspace/app_commands.php`, routes in `api.php`, handlers in `api_commands.php`.
- Scope `workspace:commands` (group `workspace_commands`, write only), in `ws_owner_scopes()`. Routes `'mcp' => false`, `dry_run`. A team devices session is refused (403): a command must belong to an application.

## Which names?
- `ws_app_command_check()`: one word of 2–20 letters (`ws_command_attempt()`'s shape), Turkish lower case, no slash; never a built-in name (folded against `ws_command_table()`) nor `/claude`, `/ai` (422). Description 1–200, usage ≤ 200, one line.
- A name is the site's: `UNIQUE (name)` under `utf8mb4_unicode_ci`, and `ws_app_command_save_check()` compares folded names (`ws_command_fold()`), so `ozetle` and `özetle` are one. Another app's name → 409 `name_taken`; twenty per app → 409 `limit_reached`. A row of a deleted app gives its name up.
- Deleting the application (`api_settings.php`) deletes its commands; `api_maintenance_purge()` sweeps orphans. Renaming keeps them.

## What happens when one is typed?
- `ws_command_run()`: a `/word` no built-in answers is looked up in `ws_app_commands_live()` (key active, not expired, holds `workspace:commands`, owner still holds it in `ws_owner_scopes()`). Then `ws_app_command_send()`: only where the owner reads the channel (`ws_app_reads_channel()`), `pg_announce('workspace.app.command')` and an ephemeral "Sent to the application …" for the writer. The line is never posted.
- Anywhere else (no live app, owner cannot read the channel) the unknown-command answer, nothing sent.
- Payload (`ws_app_command_payload()`): command, app_id, channel_id, channel, channel_kind, thread, parent_id, user_id, text (as the owner reads it), body. `ws_webhook_event_app_id()` reads `app_id`, so only that app's subscriptions hear it. The app answers with `POST /workspace/channels/{channel_id}/messages`.
- The picker, `/help`, palette and closest-name hints read them through `ws_commands($viewer)` (a person only; without one no table is read). They take free text, no argument popup, no live line.

## Tests?
- tests/workspace_app_commands_test.php (check, app_ok, find, entry, payload, audience, scope, routes).
- Not run on a real database or in the composer: the registration writes, the 409 race and the "Sent to" answer on screen.
