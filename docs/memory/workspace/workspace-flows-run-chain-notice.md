---
name: workspace-flows-run-chain-notice
description: One flow_finished inbox line per chain of runs (#3526 step 1, PR #3537, #3371): root from context chain_root or start_key, fold rule, update while unread, sent again, push, failure turn
---

## What does a chain of runs write in the inbox? (#3526, PR #3537)
- `includes/workspace/flow_run_chain_notice.php`, called first by `ws_flow_run_tell_owner()`. The chain's root is the run nobody's box started (manual, scheduled, event). Every run of the chain that ends updates the root owner's unread `flow_finished` line instead of writing its own: "Flow “X” and N flows it started: done." (N = chained runs of that owner ended on this line). The root alone still reads "Flow “X”, run N, is done.".
- Symptom fixed: a ping/pong chain wrote 8 lines (and 8 pushes) for one start (#3371).

## How is the root found?
- `ws_flow_chain_start()` writes `chain_root` as the last key of the chained run's `context` (`ws_flow_chain_context()` 6th argument; never carried from the source context). Older runs: `ws_flow_chain_root()` walks `start_key` `flow:<run>-<box>-<pass>` (`ws_flow_chain_parent_run()`) upwards; a swept parent stops at the id it names.
- Scheduled and event-started runs have no `flow:` key: they are roots, never folded.

## Which runs fold?
- `ws_flow_chain_folds()`: a run other than the root whose flow's owner owns the root's flow. Another owner's chained flow keeps the old line per run (`separate`). A root whose flow is gone folds nothing.

## How is the line found and updated?
- `ws_flow_chain_notice_line()`: the owner's latest 50 unread flow lines; the one whose notice run (`ws_scheduled_notices.action_id`) is the root or resolves to it. The notice's `created_at` is the moment the line began (kept on update); `ws_flow_chain_notice_count()` counts chained runs ended since then, by `context LIKE '%"chain_root":R}'` (or `,`), plus the run ending now.
- Update: `ws_inbox_send_again()` (notify.php) moves `created_at` only `WHERE read_at = 0` (sent again: `changed_since` reports it, see `workspace-api-sync-inbox.md`), then rewrites the notice, the bell row's title and time. A line read meanwhile is left; a new line is written (as `note_updated`).
- `ws_flow_chain_notice_plan()`: no unread line → new line, push, opens the root (or the run, if it failed). Unread, run failed, line not yet failed → update + push, the line now opens the failed run (title "A run of your flow failed", `bi-exclamation-octagon`, by `ws_inbox_flow_run_line()`). Otherwise update without push, the line keeps what it opens (first failure stays).
- Sentence: `ws_flow_chain_notice_text()`; failure: "… and N flows it started: run R of “Y” failed at the box B: text" (`ws_flow_run_failure()`).

## What does not change?
- Webhook run events, the runs list, schema. No lock: two chain runs ending in the same instant with no unread line may write two lines.
- Tests `tests/workspace_flow_run_chain_notice_test.php`.
