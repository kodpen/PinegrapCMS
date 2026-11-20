---
name: channel-activity-features
description: Plan channel-activity, features E1 to E13: the Activity tab of Channel info, what it shows of agents, requests, polls, decisions and the repository, the report's context, project and branch
---

## What is the tab?
- (E1) Channel info gets two tabs at its top (`pg-seg`, `role="tablist"`): Info, what the panel holds today, and Activity. Only in a channel with an agent or a repository link; elsewhere no tabs. The header's info button opens the tab last open in that channel (kept in the browser). The panel stays the second column that opens and closes, a phone's full screen.
- (E2) Read by every team reader of the channel (`ws_action_channel()`), as the agent card; never on the guest page. It only tells: links, no setting, no action.

## What does it show?
- (E3) A summary line, "3 working · 1 waiting · 1 not seen", from the agents' states (`ws_agent_card_state()`), and the project: the newest reported project of the channel's agents, else the linked repository's `owner/name`.
- (E4) Agents, a row each in the order working, waiting, not seen, ready, paused: the face with its state dot, name and role, the state's words. While working: who asked (linked to the message), "since HH:MM", the progress note. Then the reported model, context ("412k of 1M tokens" with a bar, marked from 90%), branch and "reported 12 min ago", muted when older than a day. The row opens the agent card.
- (E5) Requests: waiting with the oldest's age, in work, answered and failed today (`ws_agent_request_counts()`); a waiting request links to its message.
- (E6) Waiting for a person: the channel's open polls and open approvals, oldest first, one line each with its age and link; at most five, then "n more".
- (E7) Decisions today: the count and the newest three, linked; "All decisions" opens the Decisions tab.
- (E8) Repository, only with a link the reader may see: today's pull requests opened, merged and closed, checks red and green, and the last five lines with their links. Counted from the `[pr]` and `[ci]` lines the GitHub application wrote into this channel since local midnight, by that application's id.
- (E9) Plan usage: each reported window once ("Weekly 61%, resets 14.11 09:00") with the agents that reported it, under the tip "As the agent reported it; the site cannot check it."

## What is new in the report?
- (E10) `POST /workspace/agent-report` also takes `context` {used, size} (whole numbers of tokens, used at most size, size at most 10,000,000), `project` (at most 80 characters, one printable line) and `branch` (at most 120, a git ref's characters); a report needs one field. The answer route takes `context` beside `model`. A field that does not read refuses the whole report, as today.
- (E11) Stored in `ws_agents.report_usage` as one object (`windows`, `context`, `project`, `branch`); the list stored today still reads as the windows. A test holds the largest object under 1,000 bytes.
- (E12) `tools/ws_profile.php --context=USED:SIZE --project=NAME --branch=NAME`; the agent's instructions name the fields, so the instructions version changes. A session that cannot read its context sends none and its row shows none.

## How does it stay current?
- (E13) One read, panel action `ws_channel_activity` and `GET /workspace/channels/{id}/activity` (`workspace.channels.activity.get`, `workspace:read`, an MCP tool). The screen reads it when the tab opens, again on the channel's sync when a message, a request or a poll of the channel changed (at most once in 15 seconds) and every 60 seconds while open, for the ages. Nothing is read while the tab is closed.
