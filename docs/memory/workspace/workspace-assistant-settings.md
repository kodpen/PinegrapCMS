---
name: workspace-assistant-settings
description: Claude in the channels and Bulk changes in Site Settings (Assistants and agents) and on Workspace Settings: field and button names, the shared saves, an unchanged pane Save, the secret drawer
---

## Where are the two cards?
- PR #8801 (SW3 of `plans/settings-workspace-move.md`): in Assistants and agents, sections `pgset-ws-claude` and `pgset-ws-bulk`, cards in `includes/settings/workspace_assistants.php`, writes in `workspace_assistants.save.php`. The old cards (`#ws-claude`, `#ws-bulk`) keep working on `workspace_settings.php` until SW7, as SW2 left its cards ([[workspace-holiday-settings]]).
- `pg_settings_legacy_anchors()` maps `ws-claude` and `ws-bulk` to the new cards. The category's link card keeps the agents, Pinegrap AI and the MCP servers.

## How do the two screens share one card and one save?
- `ws_claude_settings_card($self_url, $viewer, $credentials, $form)` and `ws_bulk_settings_card($self_url, $viewer, $form)` draw both: with `$form` (the settings form's id) no `<form>`, fields named `ws_*`, buttons named submit buttons of the settings form.
- Claude's connection: `ws_claude_settings_input($_POST, $prefix)` reads `claude_*` (old) or `ws_claude_*` (dialog) alike; `ws_claude_settings_save()` checks everything, then writes; a refusal names its field (app_id, routine_url, token, summary_channel) and the dialog marks `ws_claude_<field>`.
- Bulk: `ws_bulk_settings_save($allowed)` writes and logs; the dialog's switch is `ws_bulk_delete`, posted with a hidden `ws_bulk_card` (an unticked box is not posted).

## Why does a pane Save not always write Claude's connection?
- The pane posts every field with each Save; a write clears `ws_claude_error` and the routine's wait. `ws_claude_settings_changed()` (pure) skips it when nothing differs; the bulk switch is written only when it differs too.
- Both cards are role 0's: for others the fields are disabled (not posted) and the save module leaves them alone.

## Which buttons run after the save?
- `ws_claude_test`, `ws_claude_key_create`, `ws_claude_key_reset` (value 1): `ws_claude_settings_post(..., true)`, a refusal a warning, as the pane is stored by then. Reset asks with an inline `confirm()`; Cancel posts nothing.

## How is a new secret shown in the dialog?
- The pane drawn after Create or Reset reads `ws_assistant_credentials` (Claude's only, role 0, never for the search index) and hands a drawer `pgset-ws-claude-secret` ([[workspace-assistant-keys]]); the card's "Show the new secret" opens it, pressed once by a script as the pane arrives.
- The drawer's Close waits for "I have copied it…", closes it and removes the secret and the button. X, Escape and the scrim only hide it until the pane is drawn again.

Tests: `tests/settings_workspace_assistants_test.php`.
