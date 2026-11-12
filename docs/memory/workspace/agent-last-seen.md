---
name: agent-last-seen
description: An agent's last seen: ws_agents.seen_at set by every call with an app agent's own key, once a minute at most, and the words on the Agents card and in "Agents in this channel" (#8828)
---

## Why is there a last seen?
- Owner (2026-11-11, #8762): "mesela tüm ajanlar ayakta mı sorulabilir". Asking wakes every agent and costs each a turn, so the site shows what it sees instead. Schema approved 2026-11-12 (`workflow/decisions-made-schema-16.md`).

## When is it written?
- `ws_agents.seen_at` (step 11.0.189, `upgrade_2026_11_0_ws_agent_seen()`, which also adds `ws_checks.app_id` for A18): every call that resolves `ws_api_agent_caller()` (the agent-requests and agent-wake routes, so every poll of `GET /workspace/agent-requests`) calls `ws_agent_seen_mark()`.
- Written at most once a minute (`ws_agent_seen_due()`, `WS_AGENT_SEEN_EVERY` 60 s). A site that has not run the step reads no `seen_at` in the row and writes nothing. 0 means never seen.

## What is shown?
- `ws_agent_seen_words()` (pure but for `lang()`), only for an `app` agent with a poll door: "Last seen a moment ago", "Last seen N min ago", "Last seen N h ago", then the date; "Not seen yet" for 0.
- A poll agent unseen for 10 minutes (`WS_AGENT_NOT_SEEN_AFTER`) or never gets the "Not seen" badge.
- A push agent keeps its last knock and its error as before; Claude and a model agent get no line.
- Where: the summary row of each agent on the Agents card of Workspace settings (`agents_settings.php`), and each agent of "Agents in this channel" in Channel settings (`ws_channel_agent_panel_row()` `seen`, `not_seen`; `workspace_channel_agents.js`).
