---
name: session-pr-binding
description: A local session in the Claude desktop app binds its PR to itself (get_status, bind_pr) right after opening it and turns archive-on-close off (set_monitor), so its PR's merge does not archive it
---

## What does a local session do right after opening its PR?
- A session in the Claude desktop app on the owner's machine binds the PR to itself with the app's PR tools. `get_status` shows whether a PR is bound: the app binds one by itself after `gh pr create` or a push of a branch that has an open PR. If none is, `bind_pr` with the PR's address (`https://github.com/kodpen/PinegrapCMS/pull/<n>`).
- Then, while `get_status` reads `auto_archive_on_close: true`, `set_monitor` with `auto_archive_on_close: false`.
- It does this for the claiming draft, so the binding is in place long before the merge.
- A cloud session follows `session-lifecycle.md` as written.

## Why?
- The account setting "Auto-archive sessions when their pull request closes" is on: the app archives a session once its PR merges or closes, unless that session's `auto_archive_on_close` is off.
- An archived session gets no message (a coordinator's message comes back "archived; unarchive it first"), and a coordinator's `unarchive_session` may be refused. The plan's next step then needs a whole new session, which reads everything again.
- A bound session that stays open is woken by its PR's merge and goes on with its next step (`session-lifecycle.md`).
- Seen on 2026-11-11 and 2026-11-12: workers that ended after one step were archived as their PRs merged; the coordinator could not reach them for the next step.

## What if the app refuses?
- `bind_pr` and `set_monitor` may ask the owner, or be refused by the auto-mode check. Do not retry and do not have another session do it: say so in the PR body ("not bound in the app"), so the coordinator knows the session ends at the merge and starts a new worker for the next step.
- Leave `auto_fix` (Auto-fix) off: it wakes the session on a merge conflict, but after the `ready` label conflicts are the merging session's (`session-lifecycle.md`).

Related: docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/coordinator-worker-brief.md, docs/memory/workflow/coordinator-limits.md
