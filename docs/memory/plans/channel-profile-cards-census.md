---
name: channel-profile-cards-census
description: PF1 census of channel-profile-cards (agent card on a name or picture in channels): where a sender's name and picture are drawn in workspace.js, what the message and member payloads carry today
---

## Where is a sender's name and picture drawn?
- A message: `messageNode` part of `assets/js/workspace.js` (about line 10630): face `avatar(message.sender)` in `.ws-msg-avatar`, name `<b>` in `.ws-msg-meta`, both through `personLink()`, which opens edit_user.php only for a person the reader may change. Badges after the name: Guest, "via", an MCP agent's `sender.title`. An agent's message has no badge: its name is "<app> (application)".
- A follow line (`sameSender()`) draws no name or face, only the clock on hover: the card hangs on the group's first line.
- Other names (pins, search, quotes, files, notes, replies) are plain text; the plan leaves them out.
- The channel head: `memberFaces()`, people round, agents square (`agentFace()`, `bi-robot` or `bi-stars`).
- Channel info: `drawInfo()` (about line 23906), "People here", "Agents here" (`channelAgents()`, `agentRow()` near line 11980), "Guests". A person's face opens `personMenu()` (header name · title, Direct message, Give a task, calendar, board, Edit user).
- The @ picker: `drawPicker()` (`workspace-mention-picker.md`), agents first from `ws_channel_app_picker()` → `ws_agent_picker_entries()`: label `@handle`, meta name, icon `bi-robot`. No role, no state.
- "Agents in this channel": `assets/js/workspace_channel_agents.js` on `ws_channel_agent_panel_row()` (`includes/workspace/channel_agents_panel.php`).
- There is no profile card or popover anywhere in Workspace today; `personMenu()` is a dropdown.

## What does a message carry?
- `sender` for an application: `ws_app_sender()` (`includes/workspace/messages.php`): `app_id`, name "<name> (application)", `title` "Integration", initials avatar, `app` true, `agent` (`ws_app_is_agent()`). Claude and Pinegrap AI get their own name, title "AI assistant", picture. No agent id, handle, role or state.
- For a person: `ws_person_brief()` (`people.php`): id, name, username, panel role, `title` (`ws_profiles.title`), avatar, presence.
- Under a message: `claude`, the list of request states (`ws_agent_request_state()`), not the agent's state in general.

## What does a member row carry?
- `ws_channel_detail()` → `agents`: `ws_channel_member_agent()`: id, app_id, handle, name, role (label), kind, assistant, active, wake, seen, seen_state (`on`, `off`, `late`, `ready`). For every reader of the channel.
- Members: person briefs; `seen` only for a channel manager (`ws_channel_member_seen()`).
- The panel row: `ws_channel_agent_panel_row()`: agent_id, kind, handle, name, active, wake_rule, wake_prefix, tier, loop_guard, seen, not_seen; no role.
- The API row: `ws_api_channel_agent_present()`: role key, status, wake, tier, loop_guard, added_by, added_at; no seen, report or state.

Facts, rights and state: `channel-profile-cards-census-facts.md`. Steps' scope and the poll: `channel-profile-cards-census-scope.md`.
