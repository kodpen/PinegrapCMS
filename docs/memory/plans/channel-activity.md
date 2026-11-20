---
name: channel-activity
description: Plan (owner 2026-11-20, agent channel poll 179): an Activity tab in Channel info that tells a channel's state: agents, requests, polls, decisions, repository numbers, reported context; KA1 to KA5
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel message 10569): a right panel that opens and closes, as Claude's activity panel, fitted to our channels; it may come as the second tab of Channel info. It tells whether the channel is in order, how many PRs merged, how many tokens each agent's context holds, the project's name.
- Owner (poll 179, message 10572): "İkisi de plana girsin"; this tab first, then the documents (`plans/channel-documents.md`). Recorded in `workflow/decisions-made-workspace-42.md`.

## What is there today?
- Channel info is the collapsible second column (`workspace/workspace-channel-panels.md`): purpose, people, agents, the reader's notifications. No tabs.
- An agent's state, its request counts of the day and its reported model and plan usage are read for the agent card (`workspace/agent-card-facts.md`, `agent-report.md`); a running request carries "since HH:MM" and a progress note (`agent-progress-note.md`).
- A linked repository writes `[pr]`, `[ci]` and label lines into the channel through the GitHub application (`api/api-github-channel-links.md`).
- Decisions have their own tab (`workspace/agent-channel-decisions.md`). Nothing tells an agent's context size, project or branch, and nothing shows the channel's state in one place: the owner reads it out of the conversation.

## What binds it?
- No table and no column: the report's new fields go into `ws_agents.report_usage` (VARCHAR 1000) as one object. A step that needs more stops and asks the architect.
- The report stays what the agent says: the site cannot check it, and the tab says so (`workflow/decisions-made-workspace-22.md`). No history, no chart, no limit: the last report only.
- Channel info stays a place that tells (`workspace/channel-settings-drawer.md`): the tab holds no setting and no action but links.
- Access only through `ws_can_read_channel()`; never the guest page. A private channel's repository link stays hidden from non-members.
- New parts by look A's recipes and the kit (`pg-seg`), never `nav-tabs` or `btn btn-…` (`ui/look-new-parts.md`).

## What did the architect decide? (2026-11-20)
- The tab shows only in a channel with an agent or a repository link; every other channel's Channel info is unchanged.
- Repository numbers are counted from the lines the GitHub application wrote into the channel, by its application id: no call to GitHub, no new store, and a typed line counts nothing.
- One read serves the screen, the API and MCP, so a coordinator learns a channel's state in one call.
- Context is reported by the session as it reads it (tokens used and the window's size); a session that cannot read it sends none.

## What is built?
- Features: `channel-activity-features.md` (E1 to E13). Steps: `channel-activity-steps.md`. Claim as `[plan:channel-activity] <step> — <topic>`.

## What is left out?
- A history or chart of states, a cost in money, alarms or pushes from the tab, settings in it, the tab on the guest page, numbers read from GitHub's API. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): built by look A's recipes and seen on dev in the agent channel, on a wide screen and a phone.
