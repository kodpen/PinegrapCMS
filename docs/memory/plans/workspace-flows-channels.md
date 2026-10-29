---
name: workspace-flows-channels
description: Plan (owner request 2026-10-27, draft): flows started from a channel's Add menu, a Completed flag with the run's result, scheduled work only as flows, no menu or form of its own
---

## What is asked?
- A run that ends gets a Completed flag (Waiting, Running or Failed until then) holding its result; a long result folds behind a down arrow. The flag shows on the canvas (last run), in the flow list and on the run's card in a channel; its colours: workspace-flows-visual-spec.md.
- The composer's Add menu (workspace.js, the `sa_new` entry today) gets "Start a flow" (the flows the member may run, each with its last flag) and "Scheduled flow" in place of "Scheduled action": a two-box flow (time start, message) in a mini flow window, published there or opened on the canvas.
- Scheduled work has no menu or form of its own: a scheduled job is a flow with a time start. Scheduled messages ("Send later") stay a composer feature (answer 1 of workspace/workspace-flows-scheduled-merge.md).

## What happens to the scheduled-action parts and API? (owner, 2026-10-27)
- Every part of a scheduled action gets a place in the designer: starts, conditions, the 12 steps, the four outcomes, delay, chain, placeholders, counts. Each has its box now: approval #2142, delay #2135, always and skipped #2249 (skipped is a condition's no), start a flow #2235, trigger values #2341, counts as outputs #2111.
- Decided (owner, 2026-10-27): the `/workspace/scheduled-actions` routes (list, get, create, update, pause, resume, cancel; `api_scheduled.php`, #1708 #1858 #1987, never released) are removed before 2026.11.0 ships, with no compatibility layer. Scheduled work goes through the flow routes (`/workspace/flows`). The #1708 rule moves to flows: only the person publishes, changes live or resumes (workflow/decisions-made-product.md). `/workspace/scheduled-messages` stays.

## Which steps? (one PR each)
- C1 the run's flag: state, one-line result, fold; on the canvas and in the list — done, #2903 (workspace/workspace-flows-run-flag.md). The run card uses its `runFlag()` in C2.

- C2 Add › Start a flow in the composer; the run's card in the channel follows the run — in progress.

- C3 Add › Scheduled flow (mini window) replaces Add › Scheduled action for new work.

- C4 the flow list: a start-kind filter (time, by hand, event, join) — done, #2391.

- C5a remove the scheduled-actions routes, their MCP tools, tests and the parity table's `api_routes` lines; rewrite workspace/workspace-scheduled-actions-api.md — done, #2107. The draft card's texts no longer name the removed route.

- C5b the `scheduled` proposal type becomes a flow draft card — done, #2852. `?start=` on `GET /workspace/flows` was already there (A1, #2122). Leftover done, #3004: Pinegrap AI's `propose_scheduled_action` keeps its drafts as flow draft cards and its texts name the Flows canvas (`workspace/workspace-scheduled-draft-proposals.md`).

- C6 every scheduled action becomes a flow in the 2026.11.0 upgrade, none left read-only; then the scheduled-action form goes. The work is merge plan slice 5: the mark on converted rows approved (#2396), 5a done (#2558).

## What was decided about existing actions? (owner, 2026-10-27)
- All of them are converted, none stays read-only or on the form: every part gets its box before 2026.11.0 ships (C6). Answer 2 of workspace/workspace-flows-scheduled-merge.md is now (b), no longer (c). Until then an existing action keeps its card and form (merge plan 4a); the other ways in move to the canvas in 4b.

## Which questions are answered?
1. Answered (#2412): run access as today (home readers, run shares; workspace-flows-shares.md); Add lists only the flows the member may run; the card goes to the channel where Add was used.
