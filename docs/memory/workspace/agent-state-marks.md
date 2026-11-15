---
name: agent-state-marks
description: An agent's state dot and role badge in a channel message and in the @ menu (PF4, PF5): the one .ws-agent-dot colour and shape map, the violet role pill, the 90% usage mark beside its name
---

## Which mark tells a state?
- Architect (2026-11-15), after PF3, PF4 and PF5 each drew another: one map for every place: working blue, waiting amber, not seen amber, paused grey, ready teal; the tones of the agents panel.
- Where the words stand beside it (a pill: the card, the panel) the tone is enough. Where they do not (the dot on a picture, in a message and in the @ menu): working and waiting are round, not seen and paused square, ready no mark; the state's words are the dot's title and accessible name.
- One definition: the `.ws-agent-dot` class with `is-<state>` (`agentStateDot()` in `workspace.js`). No place writes a second.
- The role pill is violet (`pg-pill pg-tone-violet`) everywhere, so it is never read as the blue of working.

## What does a message show? (PF4, #9629)
- A registry agent of this channel, matched by the application it writes as: its own name without "(application)", the role pill after it, the state dot in the picture's corner (`role="img"`, `state_words` as its name). `messageAgentMarks(message, channel)` (pure) reads the channel's member row (`ws_agent_card_brief()`), nothing more.
- A person's, a guest's, Claude's, Pinegrap AI's, an MCP agent's or a plain application's message stays as it was; so does an agent's that is not in this channel.
- Tests: `tests/workspace_message_agent_marks_test.php`.

## What does the @ menu show? (PF5, #9628)
- Each agent: the role after its name (the name is cut before the role), the state dot on its icon (`pickerAgentFace()`), and when a reported window is at `WS_AGENT_USAGE_HIGH` or more an amber "Usage 90%+" pill with its title.
- `ws_agent_picker_entries($rows, $briefs)` (`includes/workspace/agent_shown.php`) takes the briefs from `ws_agent_picker_briefs()`, one request count for the channel. A paused agent is not offered, so its square shows nowhere there.
- Owner (2026-11-15, channel decision 3775): the usage mark takes back "no warning on plan usage" of 2026-11-13; it is a mark and stops nothing.
- Tests: `php tools/test.php agent_shown`.
