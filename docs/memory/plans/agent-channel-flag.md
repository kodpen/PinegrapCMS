---
name: agent-channel-flag
description: Plan (owner 2026-11-08): a channel becomes an agent channel with /agent-channel, a column of its own; its decisions stay apart from the other channels' decisions; one step AC
---

## What is it for?
- The owner turns a channel into an agent channel with a slash command; a settings screen for agent channels is a later plan, once the testing is complete (`workflow/decisions-made-workspace-9.md`).
- An agent channel's decisions do not mix with the team's: agents and tests write many of them.

## Step AC (M, schema)
- Claim as `[plan:agent-channel-flag] AC — agent channel switch, decisions apart`. It builds on the decision marks of A5 (#8187, `workspace/channel-memory.md`).
- Schema: `ws_channels.agent_channel` TINYINT(1) NOT NULL DEFAULT 0 by `install_add_column()` in a re-runnable sub-step of `2026.11.0.php` (`workflow/decisions-made-schema-13.md`); read behind a ready check, since files land before the upgrade.
- `/agent-channel on|off` in the channel (bare: says the state): a channel manager only, as the tier (`ws_channel_agent_check()`); a system line says who switched it. The channel's API shape gets `agent_channel` (read only; the command sets it).
- Decisions apart: the Workspace's decisions screen leaves agent channels out and gets an "Agent channels" filter that shows only theirs; the dashboard and the summaries leave them out; the channel's own Decisions tab is unchanged. `GET /workspace/decisions` leaves them out unless `agent_channels=include|only`, or `channel_id` names one. Who may read a decision is unchanged. One pure helper builds the filter, with a test.
- Texts through `php tools/tr_add.php`; memory `workspace/agent-channel-flag.md`.

## What about mentioning an agent?
- Built: the @ picker offers the channel's registered agents and a mention wakes the agent (`workspace/agent-shown.md`, `workspace/agent-doors.md`). An application is found there only once it has an agent row: register "ADNAN Mimar" as an agent with the handle `mimar` (Workspace settings, Agents) if it has none yet.
