---
name: channel-api-census-channel
description: CA1a census (2026-11-15) of a channel's own side: panel actions beside the /workspace/ routes and MCP tools; what was counted, totals, the parts and the 33-line gap list
---

## What was counted?
- The panel: the `case 'ws_…'` actions of `includes/workspace/actions.php` (295) that the channel screen calls from `assets/js/workspace*.js`, `workspace_wtables.php`, `workspace_file_save.php`, `workspace_channel_export.php` and the GitHub card of `includes/workspace/github_links.php`.
- The API: the 248 `workspace.*` route rows of `includes/workspace/api*.php`, 80 of them `'mcp' => false` (no MCP tool); 115 are about channels, messages, polls, threads, canvases, memory, decisions, tables, groups, approvals and templates.
- Left to CA1b (its census, channel-api-census-agents): a channel's agents, an agent's calls, agent questions, the agent and person cards, model assistants.
- Out of a channel's side: tasks, notes, flows, mail, the calendar - each has its own routes.

## Where is each part?
- Messages, scheduled, polls, threads, approvals and status: `channel-api-census-channel-messages.md`.
- The channel itself, its switches, versions, members, guests, templates, export, tabs: `channel-api-census-channel-itself.md`.
- Decisions, memory, canvases, files edited in place, workflow tables, GitHub: `channel-api-census-channel-content.md`.

## What are the three lists?
- Gaps G1-G33, the panel action -> the route it would be, with its function: `channel-api-census-channel-gaps.md` (G1-G25: messages, scheduled, threads, approvals, status, the channel) and `channel-api-census-channel-gaps-content.md` (G26-G33: canvases, files, tables).
- No route on purpose or by nature, with the file that says why (and the guest links no file explains): `channel-api-census-channel-no-route.md`.
- A route that does something other than the panel: `channel-api-census-channel-differences.md`.

## What are the totals?
- Covered by a route, messages: send, reply, edit, delete, hide, react, tick, mark, pin, files, voice, forward to a channel, acknowledgements, checklist item task; polls: every part, "Other", change, take back, ask again, vote, close; threads: start, list, join, leave, follow; scheduled: list, create, cancel.
- Covered by a route, the channel: make, rename, icon, colour, topic, department, customer, archive, notifications, pin and order, groups, members, templates apply, memory, Decisions; canvas make, read, edit, save and table binding; tables, columns, rows, shares, bin.
- No route: 33 gaps; guest links and the Site Settings parts on purpose.

## What comes next?
- The architect cuts the route steps (CA2 on) from the gap list into the plan channel-api-gaps; the close-out corrects `api-panel-coverage.md`.

Related: `api-panel-coverage.md`, `workspace/workspace-api-callers.md`, `api-mcp-no-tool-routes.md`.
