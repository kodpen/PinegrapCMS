---
name: workspace-phase3-leftovers
description: Workspace phase 3 (bots): app.mentioned webhook done (#809), app commands done (#2213), app membership moved to workspace-agent-handles; ERP overdue to task done; left: designer task panel
---

## What is done?
- Phases 0–2 of the Workspace plan (skeleton, channels and tasks, plan board and conflicts) are built, plus much that came later: Pinegrap AI, `@Claude` through a routine, MCP client and remote agents, approvals, guests, meetings, notes, calendar feeds, task dependencies (`blocked_by`). Decisions: docs/memory/workspace/workspace-module-decisions.md
- Phase 4 ("AI as an application with approval cards") arrived in that form: assistants answer as applications, record changes wait as approved drafts.

## What is left of phase 3?
Not found in code on 2026-10-16; check again before starting, then one PR per item:
1. Moved (2026-10-21) to docs/memory/plans/workspace-agent-handles.md: an outside application's own tag name and place in the @ picker (step 1), its channel membership endpoints (step 4).
2. Done (#2213, decided in #2381): an application registers its own `/command`; the channel offers it beside the built-in ones and sends it to that application alone: docs/memory/workspace/workspace-app-commands.md.
3. Done (#809): `workspace.app.mentioned`, sent to the mentioned application's own subscriptions where it may read the channel, answered with `POST /workspace/channels/{id}/messages`: docs/memory/workspace/workspace-app-mentioned-webhook.md.
4. Done (#1860): ERP overdue receivable → task, `erp_overdue_followups()` behind `ERP_OVERDUE_FOLLOWUP` in config.php: docs/memory/erp/erp-overdue-reminders.md.
5. Visual designer: a "tasks of this page" panel next to the page settings.

## What rules apply when building them?
- Each feature ships with its API, scopes and webhook events in `includes/workspace/api.php`; a new scope must also enter `ws_owner_scopes()` or the endpoint answers 403.
- Webhooks for messages carry content only for channels the app is a member of; private channel rules stay.
- Writes accept `dry_run`; new endpoints that are not useful to an MCP agent set `'mcp' => false`.
