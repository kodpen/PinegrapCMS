---
name: channel-agents-panel
description: "Agents in this channel" of Channel settings (PF7, #9615): one compact row an agent, its settings asked in plain words, the state pill's words, the picture; owner's look 2026-11-15
---

## What does the panel draw?
- `assets/js/workspace_channel_agents.js` (`PGWsChannelAgents.mount()`): one compact row an agent in a look A inner card: picture, name, `@handle`, role pill (violet, as on the agent card), state pill, and a summary line "wakes when mentioned · asks before any change".
- The row opens that agent's settings under it; only one is open at a time. Nothing in a row is cut: the summary wraps, and on a phone the state pill goes under it.
- Settings: "When is it woken?" (a select, a help line for the chosen rule), "What may it do by itself?" (the tier as three radio choices, each with its line), "Stop long agent conversations" (the loop guard, a switch at the row's end with its reason), Remove from the channel, and "Save this agent" (quiet, so the drawer's own Save stays the one filled button).
- The Turkish "Kademe" is gone from the section. The Agents card of Site Settings' intro says "when it is woken and what it may do by itself" instead of "tier".

## What does the server send for a row?
- `ws_channel_agent_panel_row()`: role (label), avatar (`ws_channel_agent_avatar_url()`: https or a site path, else '' and the robot tile of `agentFace()`), state, waiting, wake_words (`ws_channel_agent_wake_words()`, shared with Channel info), tier_words (`ws_channel_agent_tier_words()`), state_words; never a door or key.
- `ws_channel_agent_panel_state()`: paused first, then the agent card's working or waiting (`ws_agent_card_state()`, one grouped request count per channel), else the last-seen state (`on`, `off`, `late`, `ready`).

## What does the state pill say?
- Each pill says what its time is (architect, channel 3871): "Seen 6 min ago", "Never seen", "Last knock …", "Always ready", "Paused", "Working on a request", "A request is waiting" or "{n} requests are waiting" (`ws_channel_agent_state_words()`).

## Who approved it?
- Owner (2026-11-15): the look, by his vote on channel message 4097.
- Tests: `tests/workspace_channel_agents_panel_test.php`.
