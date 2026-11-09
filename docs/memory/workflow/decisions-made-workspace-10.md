---
name: decisions-made-workspace-10
description: Decisions list, workspace part 10 (2026-11-09 on): application lines fold; agent activity; table bin, Excel, import; flow failure reason; team replies; channel export; agent questions; unread requests
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files.

## Which Workspace additions did the owner approve? (owner, 2026-11-09)
- All seven proposed after a review of the open Workspace plans. Each has its plan in `plans/`, which holds the detail.
- Application lines fold (`workspace-app-lines-fold.md`): a run of three or more one-line messages from one application that is no agent shows as its newest line with "+N lines"; each reader chooses Folded, All or Hidden per channel, in the browser. Why: 268 of the agent channel's 283 messages on dev were CI lines, which stay in that channel (owner, 2026-11-08). Stored messages, the API and the counts do not change.
- Agent activity (`agent-activity-usage.md`): the Agents card shows each agent's requests by state, its next wake and its waiting and failed requests, with Cancel, Ask again and Knock now. Token counts and a monthly token limit per model assistant follow; their columns were approved on 2026-11-10 (`decisions-made-schema-14.md`). Why: a request's state showed only under its message, and a model assistant spends the site's provider key uncounted.
- Table bin (`tables-bin.md`): a deleted workflow table or row is kept for the Recycle Bin's days (30 by default) and can be restored. This replaces "a delete is final". Why: people, agents and the API delete rows.
- Table as Excel and an import route (`tables-xlsx-import-route.md`): whoever may view a table downloads it; the API and MCP import a file or a note's table, with a dry run. Why: teams pass tables around as Excel, and the import was on the panel only.
- Why a flow box failed (`workspace/workspace-flows-failure-reason.md`): the failure names the value that came empty and its box, with the fix one click away; a row read that finds nothing stays done. Why: five runs on dev failed with "The message is empty." and nothing named the row box.
- Team saved replies (built: #8405, #8402; `workspace/workspace-saved-replies-team.md`): every member inserts them, managing takes a right of its own, a personal reply wins a shared shortcut, and the list opens in the e-mail reply box too. Why: each person wrote the same answers to customers.
- Channel export (`channel-export.md`): whoever may read a channel exports what they see as Markdown or a print page, with its decisions and file list, at most 5,000 messages, an audit line each time. Why: notes export, channels did not.

## Whom does an agent's question ring? (owner, 2026-11-09)
- A checklist question or a poll that an application posts rings the people it mentions, whatever their notify level, and the channel's owners when it mentions nobody, as an approval request rings its approvers. Why: a member on "mentions only" never saw an agent's question in the bell. Plan: `plans/agent-channel-questions.md`.

## Is the agent channel tried live? (owner, 2026-11-09)
- Yes, on dev with the architect's application: registered as `@mimar` with a poll door and added to the agent channel (wake on a mention, tier 1). A mention, a `/wake` and the brief are tried once each, and what happened goes to `workspace/`. A job of its own on the owner's machine (`plans/agent-channel.md`).

## What happens to requests an application agent does not read? (owner, 2026-11-10)
- A schedule that comes due while an earlier wake of that agent still waits makes no new request (the schedule moves on), and a queued request not claimed within a day is cancelled by the hourly job, as Claude's, Pinegrap AI's and a model assistant's are: under a mention the line says the agent did not take it within a day, a wake closes silently. Why: in the live trial a one-minute schedule made a request at every due time while the earlier ones waited, and a mention said "Waiting" for ever. #8411; not built (`plans/agent-channel-trial.md`).
