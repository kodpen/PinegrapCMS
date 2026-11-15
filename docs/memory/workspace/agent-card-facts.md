---
name: agent-card-facts
description: An agent's card, state dot and usage mark in a channel (agent_card.php, PF2): state order, request counts, the 90% mark, the ws_agent_card action, the API row fields
---

## What does a member row of an agent carry now?
- `ws_channel_member_agent($row, $now, $counts)` adds `ws_agent_card_brief()`: `role_key`, `state`, `state_words`, `waiting`, `usage_high`. `ws_channel_member_agents()` reads the counts of all the channel's agents in one query (`ws_agent_request_counts()`). Without counts the brief is left out (the old callers).
- Sent in `ws_channel_detail()` to every reader of the channel, never on the guest page.

## How is the state read?
- `ws_agent_card_state()`, first that holds: `paused` (status not active), `working` (a request `sent` or `running`), `late` (not seen: `seen_state` late, `agent-last-seen.md`), `waiting` (a `queued` request), else `idle` ("Ready").
- Not seen goes before waiting: a queued request of an agent that is down says nothing new.
- `ws_agent_request_counts()`: `ws_ai_requests` by `ws_agent_request_value()`, open ones plus `answered` and `failed` with `answered_at` since local midnight (`strtotime('today')`); pure part `ws_agent_request_counts_of()`.

## When is the usage mark set?
- `WS_AGENT_USAGE_HIGH` (90): any window of a report younger than `WS_AGENT_REPORT_STALE_AFTER` at or above it (`ws_agent_card_usage_high()`). A mark only; it stops nothing.
- Owner (2026-11-15, channel decision 3775): the mark takes back "no warning on plan usage" of 2026-11-13 (`agent-report.md`).

## What does the card read?
- Panel action `ws_agent_card` (channel_id, agent_id), answered by `ws_channel_agents_action()` for any team reader of the channel (`ws_action_channel()`); `ws_agent_card()` gives id, app_id, handle, name, kind, role, seen, model, windows (label, percent, "resets …", high), `reported` ("As the agent reported it … the site cannot check it."), stale, today (answered, failed), last_message (id, when), channels, tier, wake, and the brief.
- `last_message`: the agent's application's newest not-deleted message in the channel (`ws_agent_last_message()`, through `idx_channel`).
- `channels`: its `ws_channel_agents` rows the reader may read (`ws_agent_reader_channels()`, `ws_can_read_channel()`).
- What it may do and what wakes it in plain words come from the agents panel's own word functions (PF7, #9615); the card sends the tier and the member row's wake words until both have landed.
- The model and windows only for an `app` agent that reported (`agent-report.md`).

## And the API?
- `ws_api_channel_agent_present($channel_id, $row, $counts, $now)` adds `state`, `waiting`, `seen_at`, `model`, `usage` (name, percent, resets_at), `reported_at`, `usage_high`; `WorkspaceChannelAgent` declares them. The list route reads the counts once.
- Tests: `tests/workspace_agent_card_test.php`.
