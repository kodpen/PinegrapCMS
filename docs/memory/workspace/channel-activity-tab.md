---
name: channel-activity-tab
description: The Activity tab of Channel info (#10751, #10813, #10810, #10803): when it shows, what it tells, the one read behind it, how repository numbers are counted
---

## What is it, and why?
- Owner (2026-11-20, agent channel poll 179, message 10572): a channel's state in one place, as Claude's activity panel: agents, merged PRs, each agent's context, the project (`workflow/decisions-made-workspace-42.md`).
- Channel info gets two tabs at its top, Info and Activity (`pg-seg`, `role="tablist"`), only in a channel with an agent or a repository link the reader may see (`activityTabsShow()`, `repository_linked` on the channel detail); elsewhere no tabs. The info button opens the tab last open there (`pg-ws-info-tab-<id>` in the browser).
- It only tells: links, no setting, no action. Every team reader of the channel; never the guest page.

## What does it show?
- A summary line, "3 working · 1 waiting · 1 not seen", and the project: the newest one an agent reported, else the linked repository's `owner/name`.
- Agents, ordered working, waiting, not seen, ready, paused: face with state dot, name, role, state; while working who asked (linked), "since HH:MM", the progress note; the reported model, context ("412k of 1M tokens" with a bar, marked from 90%), branch and "reported … ago", muted after a day. The name opens the agent card.
- Requests (waiting with the oldest's age, in work, answered and failed today); polls, agent questions and approvals waiting for a person, oldest first, five then "n more"; today's decisions, count and newest three, "All decisions" opens the Decisions tab.
- Repository: today's pull requests opened, merged, closed, checks green and red, the last five lines. Plan usage: each window once with the agents that reported it, under "As the agent reported it; the site cannot check it."

## Which read is behind it?
- `ws_channel_activity($viewer, $channel, $now)` (`includes/workspace/channel_activity.php`): panel action `ws_channel_activity` (`channel_agents_panel.php`) and `GET /workspace/channels/{id}/activity` (`workspace.channels.activity.get`, `workspace:read`, an MCP tool, schema `WorkspaceChannelActivity`). One read for the screen, the API and a coordinator.
- A part with nothing is null and the screen leaves it out.
- Tests: `tests/workspace_channel_activity_test.php`, `workspace_channel_activity_js_test.php`, `github_links_test.php`.

## How are the repository numbers counted?
- Architect (2026-11-20): from the `[pr]` and `[ci]` lines the GitHub application wrote into the channel since local midnight, by its application id (`github_channel_app`): no call to GitHub, no store; a typed look-alike counts nothing (`ws_channel_activity_repo()`, `pg_github_line_read()`, `pg_github_day_counts()`). The channel's newest 5,000 messages are looked through.
- A private channel's link and numbers only for its members.

## When does the screen read it?
- `assets/js/workspace_channel_activity.js` (bundled in `workspace.min.js`), mounted from `drawInfo()`: when the tab opens, on the channel's sync when a message, a vote or an answer changed (at most once in 15 seconds) and every 60 seconds while open. Nothing while the tab is closed.

## What was left out?
- A history or chart of states, a cost in money, alarms, settings in the tab, the guest page, numbers from GitHub's API. Each is a later yes.

Related: docs/memory/workspace/agent-report.md, docs/memory/workspace/agent-card-facts.md, docs/memory/workspace/workspace-channel-panels.md
