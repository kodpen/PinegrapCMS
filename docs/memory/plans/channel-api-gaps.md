---
name: channel-api-gaps
description: Plan (owner 2026-11-15, agent channel message 5039): the API side of channels and the agent channel completed: two censuses of what the panel does that no route does, then one small step per gap
---

## What is asked?
- Owner (2026-11-15, agent channel message 5039): "arayüz geliştirmelerinden önce ajan kanalları ve kanalllarla ilgili özellikleri öne alalım. api taraflarını tamamlayalım."
- The API was last compared with the panel on 2026-11-07 (#7967, `api/api-panel-coverage.md`). The agent channel and most of what channels gained since landed after it, each with the routes its own plan named; nobody has compared the whole.

## Which steps are there?
- CA1a (S, no product code) Census of a channel's own side. Every thing a person does in or about a channel in the panel (the Workspace's actions, the channel's menus, drawers and tabs) beside the routes under `/workspace/` and their MCP tools: the route that does it, or "none". Output `api/channel-api-census-channel.md`.
  - At least: messages (send, edit, delete, reactions, pins, threads, files, scheduled, forwarded), polls (option parts, "Other", change, take back, ask again, close), the channel itself (make, rename, icon, colour, the settings drawer's switches, versions, archive, members, guests), Decisions and the channel's memory, the canvases and workflow tables of a channel, the GitHub link.
- CA1b (S, no product code) Census of the agents' side, beside CA1a. Output `api/channel-api-census-agents.md`.
  - The channel's agents (add, remove, tier, wake rule, loop guard), an agent's own calls (requests, brief, claim, answer, fail, report, wake, schedule, questions, approvals, proposals), agent questions and their answers, the facts of the agent card and the person card, model assistants in a channel.
  - Also what an agent meets when it finds its way with the API alone: a call the brief or an error names that does not exist, a refusal that names no next step.
- Each census ends in a gap list (the panel's action -> the route it would be, with the function that already does the write), a list of what has no route on purpose with the memory file that says why, and a list of what a route does differently from the panel.
- CA2 and on: one step per gap or per group of one file's gaps, cut by the architect from the two lists and written here.
- Last (S): close-out: the topic files, `api/api-panel-coverage.md` corrected, this plan deleted.

## What does every step keep?
- What has no route on purpose stays so: Site Settings and with it the Agents card (`api/api-settings-no-endpoint.md`), a write the person must make themselves (`workspace/workspace-api-callers.md`), a guest's reach. Such a gap is listed, never built; where it matters the architect asks the owner.
- A new route reuses the panel's write path (`api/api-panel-coverage.md`, "How is a write route for a panel screen added?"), has a dry run where it writes, its schema row and its tests.
- A route gives an application no more than its scopes and its channel membership give today. No new scope, table or column without the architect.
- Claim as `[plan:channel-api-gaps] <step> — <topic>`.
