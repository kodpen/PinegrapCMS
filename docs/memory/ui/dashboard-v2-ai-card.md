---
name: dashboard-v2-ai-card
description: New dashboard Assistant card (cards/ai.php, pg_dash_cards_assistant.js): connected or not, the proposals waiting for the person, giving Claude a job through ws_send; the daily summary held on #5014
---

## Where is it?
- `includes/dashboard/v2/cards/ai.php`; renderer `pgDash.renderers.ai` in `assets/js/pg_dash_cards_assistant.js` (bundle `pg_dash.min.js`); styles in the "Assistant card" section of `pg_dash.src.css`. Tests: `tests/dashboard_v2_ai_test.php`. Gate `workspace` (`pg_dashboard_workspace_rights()`).

## When is it "connected"?
- `ws_claude_schema_ready()` and `ws_claude_ready()` (switched on, application, routine URL and token, API on): header chip "Claude · connected". Otherwise "Not connected" (screen 15): Connect Claude (`workspace_settings.php#ws-claude`) for a person with the Workspace settings right, Connections (`api_settings.php`, the OAuth/MCP connections) for role < 3, a line naming who connects it for everyone else, and the MCP note.
- The header chip fills the frame's `.pg-dash-card-chip` slot from the renderer.

## Which proposals are counted?
- Record changes `ws_ai_changes` pending with `requested_by` the person (only they apply them, `ws_change_apply_check()`), in a channel they may post in, or on a task they still see; task drafts `ws_ai_drafts` pending from the person's own `ws_ai_requests`, in a channel they may post in. Any agent's, not only Claude's.
- Chips "Task: …" / "Change: …" (a change's reason, else "Update/New/Delete <kind>"), newest four, cut to 60 characters; each opens its card (`workspace.php?channel=&message=`, or `workspace_tasks.php?task=`). The title carries the full count, "n more in the Workspace" below.

## How is a job given?
- The box posts `<@app:N> text` with the Workspace's own `ws_send` (api.php, checks channel, person and Claude's per-person limit), then `ws_claude_kick`. Nothing in the card writes.
- Channel: the one the person last asked Claude in, else the oldest public unarchived channel; only where they may post and `ws_claude_channel_allowed()`. None: a line saying a channel manager can allow Claude. The note names the channel; after sending, "Sent to #x" with an Open link to the message.

## What is not built?
- The daily summary (question 3: a setting with an hour, off by default). Where the setting and the summary are kept is architect issue #5014; step N11c.
