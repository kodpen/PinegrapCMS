---
name: agent-channel-flag
description: Agent channel switch (ws_channels.agent_channel, #8440): /agent-channel on|off, its decisions kept apart on the decisions screen, the dashboard and GET /workspace/decisions
---

## What is an agent channel?
- A channel whose `ws_channels.agent_channel` is 1 (step 11.0.180, `upgrade_2026_11_0_ws_agent_channel()`; owner 2026-11-08, `workflow/decisions-made-schema-13.md`, `decisions-made-workspace-9.md`). Every channel starts off. Agents and tests write many decisions there, so they do not mix with the team's.
- An explicit switch, not "a channel with an agent member": a clear test for keeping its decisions apart. A settings screen for agent channels is a later plan, once the testing is complete.
- Code: `includes/workspace/agent_channel.php`. Read the column only after `ws_agent_channel_ready()` (files land before the upgrade); without it every channel reads as a team channel and the command answers "The database has to be updated first."

## How is it switched?
- `/agent-channel on|off` (also `/ajan-kanalı`, `/ajan-kanali`; on/aç/açık, off/kapat/kapalı) in the channel: a channel manager only (`ws_can_manage_channel()`, as the agent tier); a system line names who switched it. Bare, it tells the person the state. A discussion (kind `thread`) is refused: switch its channel.
- The API shows it read only: `agent_channel` on the channel shape (`ws_channel_brief()`, `ws_api_channel_present()`); no route sets it.

## Where are its decisions kept apart?
- One pure helper, `ws_agent_channel_filter($channels, $mode, $named)` (`tests/workspace_agent_channel_test.php`): mode '' leaves agent channels out, `include` takes them too, `only` takes them alone; the channel the request names is kept whatever it is.
- `ws_timeline()` applies it after the reader's channels, so the decisions screen (`workspace_timeline.php`), its per-channel counts, the staff count of private channels (`ws_timeline_hidden()`) and the dashboard's latest decisions (`home.php`) leave agent channels out. The screen's "Agent channels" toggle (`agents=1` in the address) sends `agent_channels=only`; it shows once the person may read an agent channel (`agent_channels` count on the first page).
- `GET /workspace/decisions` leaves them out unless `agent_channels=include|only` or `channel_id` names one.
- Unchanged: the channel's own Decisions tab, its board and brief, the agent's memory digest, and who may read a decision.
