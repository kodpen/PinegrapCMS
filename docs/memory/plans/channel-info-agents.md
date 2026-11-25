---
name: channel-info-agents
description: Plan (owner 2026-11-25): Channel info's two agent lists become one calm block, the same under both tabs, a row opening for its detail; one step, CI1, built as drawn
priority: 5
---

## What is asked?
- Owner (2026-11-25, agent channel, request 2951): Channel info has two tabs and each tells something else about the same agents; one agents part, a little more detailed, common to both tabs.
- Owner (2026-11-25, request 2952): modern and calm; the texts, buttons and badges of a row must not crowd it.

## What is there today?
- Info: `drawInfo()` in `assets/js/workspace.js` draws "Agents here · n" with `agentInfoRow()` from `channel.agents` (`ws_channel_member_agents()`): name, role, badge, `@handle · <wake rule>`, last seen (`workspace/channel-members-agents.md`).
- Activity: `drawSummary()` and `drawAgents()` in `assets/js/workspace_channel_activity.js` from `ws_channel_activity()`: state, who asked, since, the note, model, context, branch, reported (`workspace/channel-activity-tab.md`).
- Neither list has the other's facts, so the reader changes tab and reads the list again.

## What does the step build?
- CI1 (M): the one block, as drawn in `workspace/channel-info-agents-drawing.md`; the drawing wins where it differs from the lines below.
- The read: each agent of `ws_channel_activity()` also carries its handle, the wake rule's words, the last seen's words and state and its count of waiting requests, taken from the member row (`ws_channel_member_agent()`), no query more for an agent; the assistants that can be asked in the channel follow as rows. `WorkspaceChannelActivity` declares the fields.
- The screen: one function draws the block. In a channel with a registry agent `workspace_channel_activity.js` mounts it once under the tabs, above both panes; elsewhere `drawInfo()` puts it where "Agents here" is. It draws at once from `channel.agents` and fills in from the read.
- A row is a button with `aria-expanded`; which rows are open and whether "n more" was pressed are kept while the panel is open, through a redraw too. The ring is drawn in SVG from the context's per cent.
- The read runs while the panel is open on either tab in a channel with a registry agent, on today's brakes (when it opens, at most once in 15 seconds on the channel's sync, every 60 seconds); the Activity pane draws its other parts, the project line first, from the same answer.
- Taken out: Info's agents list in `drawInfo()`, `drawSummary()` and `drawAgents()` with their styles, and the texts no other place uses.
- Kept: the discussion's people panel and its list (`agentInfoRow()`, `.ws-info-agents`), the agent card on messages and the channel head's faces, the state dot's one definition (`.ws-agent-dot`), every label that exists today in its own words.
- New texts through `php tools/tr_add.php`: "Agents", "Manage agents", "{n} more", "no report yet", "{n} requests in its queue" with its singular, "for {name}, since {time}", and the facts' labels "Doing", "Queue", "Handle", "Wakes", "Model", "Context", "Branch", "Seen", "Reported", "Not yet".
- No schema, no setting, no new route.

## How is it checked?
- Tests: the read's new fields and the assistants' rows (`tests/workspace_channel_activity_test.php`), the pure parts of the row (line 2 of each state, the facts an opened row has, the order, the six) in `tests/workspace_channel_activity_js_test.php`, `tests/workspace_channel_info_test.php` kept green.

## What closes the plan?
- The step's PR corrects `workspace/channel-activity-tab.md`, `workspace/channel-members-agents.md` and `workspace/workspace-channel-panels.md` to the block and deletes this file; the drawing and its memory stay.
