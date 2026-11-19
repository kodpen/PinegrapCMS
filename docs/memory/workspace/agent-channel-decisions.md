---
name: agent-channel-decisions
description: An agent channel's Decisions tab (This channel / All agent channels) and its agents' brief read the decisions of every agent channel the reader may read (owner poll 151, GM4)
---

## Why?
- Owner (2026-11-19, poll 151, `workflow/decisions-made-workspace-39.md`): a new agent channel reads the decisions made in the other agent channels, so the owner can leave an old one for a new one. A deleted channel's decisions go with it; an archived channel's stay.

## Which channels are read?
- `ws_agent_channel_readable($viewer)`: `ws_timeline_channels()` (public, the reader's private ones, a staff inspection; archived kept; no discussion or mailbox) under `ws_agent_channel_filter(…, 'only')`. Who may read a decision does not change.
- `ws_agent_channel_decision_ids($channel, $readable, $scope)` (pure): every readable agent channel plus the channel itself, only when the channel is an agent channel and the scope is `agents`; otherwise the channel alone.

## How does the Decisions tab choose?
- In an agent channel the tab draws a `pg-seg` above the cards: This channel / All agent channels (`decisionScope()`, kept while the screen is open, not stored). `ws_channel_decisions` takes `scope=agents` (`ws_agent_channel_scope()`), reads `ws_channel_decisions($viewer, $id, $ids)` with the answered agent questions of the same channels, and answers `scope`.
- A card from another channel names it ("In #name", `ws_agent_channel_decision_names()`, `channel_name`); Show in the conversation opens that channel.
- A team channel's tab is unchanged: no choice, its own decisions.

## What does the brief carry?
- In an agent channel `ws_memory_brief()` always reads across: the newest ten decisions of the readable agent channels under "Decisions in the agent channels (one made in another channel names it first):", a line from another channel starting `#name: `; the closing count names `workspace.decisions.list (agent_channels only)`. With no other agent channel readable it stays "Decisions in this channel:".
- `ws_memory_digest(…, $across)` is pure (`tests/workspace_channel_memory_test.php`); `ws_agent_question_decisions()` and `ws_agent_question_decision_messages()` take several channels (`ws_agent_question_channels_sql()`).

## How is it tested?
- `tests/workspace_agent_channel_test.php` (scope, ids, names, the shared switch), `tests/workspace_channel_settings_test.php` (`decisionScope()`, `agentChannelSwitchShown()`, the form's wiring).
