---
name: dashboard-v2-ai-card
description: New dashboard Assistant card (cards/ai.php, pg_dash_cards_assistant.js): connected or not, the proposals waiting for the person, giving Claude a job through ws_send, the chat box; the daily summary
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

## How does the chat box work?
- A8b, #8575: a section under the job box, "Chat with an assistant", on the same catalogue: no list, store or tool of its own. Choice made by the session (owner away, architect: "decide what fits best").
- Who: the `ws_assistants()` entries a message can ask (`pg_dashboard_v2_ai_chat_askable()`): Claude and Pinegrap AI by their tag and channel switch, an MCP alias as `@alias` where Pinegrap AI may be asked, a registry agent by `<@app:N>` where the channel allows it. A picker shows "name · #channel" when there are several.
- Where: per assistant the first channel of `pg_dashboard_v2_ai_channels()` (the one the person last asked any assistant in, then the oldest public ones; unarchived, may post) where it may be asked. The note says everybody who reads the channel sees the talk.
- How: the first line is a new message `<tag> text` through `ws_send`, the next ones reply to it (`parent_id`), each with the tag; then the kick the answer names (`claude`, `ai`, `agent`). Text limit `pg_dashboard_v2_ai_chat_max()`: 2000, or `WS_MESSAGE_MAX` less the tag.
- Answers: `ws_sync` (`since_id`, `panel`) every 8 s for 10 minutes after the last line; a message replying to one of the chain joins it and shows as plain text (DOMParser) with an Open link. Only what the person may read comes back. Then "No answer yet…".
- Not done: the chat when Claude is not connected (the card shows its offline view); keeping the talk after a reload.

## Where is the daily summary?
- Above the proposals when it is on (question 3: a setting with an hour, off by default; #5080): `workspace/workspace-claude-daily-summary.md`.
