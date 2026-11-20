---
name: agent-channel-flag
description: Agent channel switch (ws_channels.agent_channel, #8440): command, Channel settings, New channel form; decisions kept apart from general screens and API lists; tasks: agent-channel-readers
---

## What is an agent channel?
- A channel whose `ws_channels.agent_channel` is 1 (step 11.0.180, `upgrade_2026_11_0_ws_agent_channel()`; owner 2026-11-08, `workflow/decisions-made-schema-13.md`, `decisions-made-workspace-9.md`). Every channel starts off. Agents and tests write many decisions there, so they do not mix with the team's.
- An explicit switch, not "a channel with an agent member": a clear test for keeping its decisions apart.
- Code: `includes/workspace/agent_channel.php`. Read the column only after `ws_agent_channel_ready()` (files land before the upgrade); without it every channel reads as a team channel and the command answers "The database has to be updated first."

## How is it switched?
- `/agent-channel on|off` (also `/ajan-kanalı`, `/ajan-kanali`; on/aç/açık, off/kapat/kapalı) in the channel; bare, it tells the person the state.
- The Agent channel switch on the General tab of Channel settings and in the New channel form (owner poll 152, GM4 #10471): `agentChannelSwitchShown()` draws it for a manager (`can_manage`), never on a discussion or the note-to-channel form; `agent_channel` rides `ws_channel_update` / `ws_channel_create`.
- All three go through `ws_agent_channel_set()`: a channel manager only (`ws_can_manage_channel()`), a discussion (kind `thread`) refused, a system line naming who switched it.
- The API shows it read only: `agent_channel` on the channel shape (`ws_channel_brief()`, `ws_api_channel_present()`); no route sets it.

## Where are its decisions kept apart?
- One pure helper, `ws_agent_channel_filter($channels, $mode, $named)` (`tests/workspace_agent_channel_test.php`): mode '' leaves agent channels out, `include` takes them too, `only` takes them alone; the channel the request names is kept whatever it is.
- `ws_timeline()` applies it after the reader's channels, so the decisions screen (`workspace_timeline.php`), its per-channel counts, the staff count of private channels (`ws_timeline_hidden()`) and the dashboard's latest decisions (`home.php`) leave agent channels out. The screen's "Agent channels" toggle (`agents=1` in the address) sends `agent_channels=only`; it shows once the person may read an agent channel (`agent_channels` count on the first page).
- `GET /workspace/decisions` leaves them out unless `agent_channels=include|only` or `channel_id` names one.

## Where are its tasks and conversations kept apart?
- The task lists, the counts, the Work Calendar's Conversations layer, the scheduled counts and every general reader left as it is: `agent-channel-readers.md`. A task an application created is marked apart from the channel: `task-app-mark.md`.

## What is unchanged?
- Who may read a decision. An agent channel's own Decisions tab and its brief read every agent channel's decisions (`agent-channel-decisions.md`).
