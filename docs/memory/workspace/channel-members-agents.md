---
name: channel-members-agents
description: A channel's agents among its members (#8936): agents as squares in the head's faces, People here and Agents here in Channel info, last seen; a person's time only for a manager
---

## Where do a channel's agents show beside its people?
- The channel head's faces (`memberFaces()` in workspace.js): up to four people's round faces, then up to four agents as rounded squares with the agent mark (`agentFace()`, `.ws-agent-face`), and one count for both.
- Channel info (`drawInfo()`): "People here · n", Add people, then "Agents here · n": Claude and Pinegrap AI where they can be asked, then the channel's registry agents (`channelAgents()`). "Agents in this channel" in Channel settings stays where agents are added and changed.
- An agent's row: square face, name with the badge "AI agent" ("Assistant" for Claude, Pinegrap AI and a `model` agent), "Paused" for an agent not active, under it `@handle · <wake rule>` ("wakes when mentioned", "wakes on every message", "wakes on [ci]", "wakes on replies to it"), its last seen at the right.
- A manager gets "Manage" under the list: Channel settings opened scrolled to "Agents in this channel" (`channelForm(channel, { agents: true })`, `PGWsChannelAgents.mount(…, { reveal })`).

## What does the server send?
- `ws_channel_detail()` adds `agents` (`ws_channel_member_agents()`, one `ws_channel_member_agent()` per row of `ws_channel_agents()`) for every reader of the channel, and `seen` on each member only for a channel manager (`ws_channel_member_seen()`).
- An agent's last seen, `seen_state`: a poll `app` agent's `seen_at` in short words (`ws_seen_ago_words()`), `on` (teal dot) under 10 minutes, `late` (amber pill) at 10 minutes or never (`WS_AGENT_NOT_SEEN_AFTER`); a push agent "Last knock …" from `door_last_knock`, `late` while `door_error` is set; Claude, Pinegrap AI and a `model` agent "Always ready".
- A person's: "Online" while `ws_presence()` reads online, else `user.user_online_timestamp` in the same short words; no new column. Other members keep the online dot on the face.

## Why?
- Owner (2026-11-12, #8880): a manager should see who is registered in a channel and follow agents that go down; a person's time only for managers (`workflow/decisions-made-workspace-19.md`). The look: `plans/panel-design-language-dialogs.md`.
- Tests: `tests/workspace_channel_members_test.php`, `tests/workspace_channel_info_test.php`.
