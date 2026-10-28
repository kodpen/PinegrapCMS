---
name: workspace-flows-run-view
description: The run view of the flow canvas (workspace_flow.js, PR #1555): Run, the runs list, a run shown box by box, the values each box received, how it is read again, what a failed read shows
---

## How is a run started?
- Run (live, version > 0, edit or run access) asks, then starts once (`state.starting`). Primary only without "New flow".
- Its token goes as `start_token`: `token` is the CSRF token, which `api()` lets a data key replace (every start was refused). Kept until answered, so a start sent again gets the first one's run.

## How are the runs listed?
- "Flow runs" lists them (`ws_flow_runs_list`, 20 a page) while nothing is selected; pressed only while the list or a run shows (`runsShown()`).
- A load that fails keeps its reason (`state.runs.error`): with nothing listed, the side panel shows it and Try again. It used to say "This flow has not run yet." The toast stays.

## How is a run shown?
- A run picked: its button disabled meanwhile, only the last pick shown (`openSeq`). Its version's graph read-only; draft, selection, view wait in `state.stash`, saves keep sending the draft (`draftOf()`).
- Each box: `wsf-run-<state>` tone, mark and word (none = not reached), result, a waiting task's link (`?task=`, `K.openTask()`, then `ws_tick`). Taken arrows marked; outline in words.
- Cancel (`can_cancel`) asks. Close and "All runs" bring the flow back as it was.

## What did a box receive? (T4, PR #2650)
- `runReads()` under the box's result: per field, each thread into it (`dataThreads()` of the run's graph) with `runValue()`: `text`, `outcome`, `status` from the step's result, outcome, status, else `steps[].outputs`; a constants box's row; a derived box's value is not in the run's answer, so it is named (`fl_run_value_derived`), never worked out again in the browser; empty for a box skipped or not reached, "not given yet" while it runs. A `{{trigger.*}}` value is not in the run's answer: named, never guessed.
- Once the box ran, the field as filled (`runFilled()`); "Show the template" (`state.runTemplates`) the field as written. The outline's reads (`drawReads()`) add the value. Test `test_workspace_flows_canvas_run_lists_the_values_received`.

## How is it read again?
- `ws_flow_run_get` after 2 s, ×1.5 up to 30 s while nothing changes; stops when ended or hidden, looks at once when shown. `poll.busy` is a run id: a slow look at the run before froze the next. `due` sends `ws_tick`. Polite live region.

## What does a failed read show?
- `pollFailed()` sorts by `error.code`, which `api()` sets only on an error read from a JSON answer.
- No answer (network, a server error page): the next look waits twice as long, up to 60 s. The run bar says the run could not be read just now, with Try again, until a look succeeds; screen readers hear it once.
- A JSON refusal (flow binned with its runs, channel no longer read, workspace off, session ended or token changed) is final: the bar shows the server's reason and that the run is no longer followed (the side panel says so in place of "Still going"), Cancel goes, and neither the tick nor a tab shown again looks (`schedulePoll()`). It used to stop without a word, still "Still going" with Cancel.

## How is it tested?
- `tests/workspace_flows_canvas_test.php` runs `pollRun()`, `loadRuns()` and their drawing from the script under Node, with stand-ins for the page (skipped without Node). No headless browser harness is in the repo.
