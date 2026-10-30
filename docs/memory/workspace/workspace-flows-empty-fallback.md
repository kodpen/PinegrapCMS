---
name: workspace-flows-empty-fallback
description: The per-thread "When empty, write" fallback of a Flows value (T6, PR #3729, #2415 2a): token syntax, where it is filled, which references take it, the warning rule, the chip
---

## How is a fallback written?
- In the token itself, so threads still come only from the fields: `{{node.<id>.<key>|<text>}}` (also `{{trigger.<key>|<text>}}`). Group 3 of `WS_STEP_VALUE_PATTERN` (steps.php), group 4 of `VALUE_TOKEN` (workspace_flow.js); groups 1 and 2 are unchanged.
- In the text `\`, `{`, `}` and `|` are written `\\`, `\{`, `\}`, `\|`, so `}}` never ends it early; one line. `ws_step_value_token($box, $key, $fallback)` and JS `tokenWith()` write it (trimmed, cut to `WS_STEP_FALLBACK_LENGTH` = 100 characters; empty means none); `ws_step_value_fallback($match)` and `tokenFallback()` read it back; `tokenBare()` drops it. The pattern's limit is 400 bytes (PHP reads bytes).
- Per thread (decision 2a of #2415): each place a value is written has its own fallback.

## When is it used?
- `ws_step_value_of($match, $values)`: the value as text, the fallback when the run gives none or an empty one (a skipped box, one not run, an empty output). A box that ran with a value never shows it.
- `ws_step_fill_nodes()`: then escaped for its target like any value (`ws_step_fill_value()`, D3), so a tag or `{{date}}` in it stays text.
- `ws_step_url_fill()`: the fallback obeys the address rule (#3410): digits only, else the run refuses it; `ws_flow_reference_problems()` refuses a non-digit fallback in a `urls` key at save.
- `ws_flow_derived_fill()`: a derived box's input takes it as it is (the reader escapes).
- `ws_step_fill_missing()` (the box try's "empty" list) leaves out a reference with a fallback.

## Which references take none?
- First cut: a condition's `rule.ref` (its normalizer keeps only an exact `{{…}}`), a loop's list and an End box's gathered value (`ws_step_value_single()`: one reference, no fallback, else `bad_config`) read a value as it is.

## What about the warning?
- `skippable_reference` is raised only when one place in the box's texts reads the value without a fallback (`$bare` in `ws_flow_reference_problems()`); references are named without their fallback. A fallback never excuses `bad_reference`. The derived-box warning is unchanged.

## What does the canvas do?
- `dataThreads()` keys a thread by the bare token (`thread.token`) with `thread.fallback` (the first place's); from, to and the dot stay as without one. `bindTargets()` uses `holdsToken()`.
- The chip (`valueChip()`) reads "Box › Value, else "text"", dashed only while skipped without a fallback; clicking a known chip in a draft opens "When empty, write…" (`askFallback()`), which rewrites its token; an empty answer clears it.
- Outline (`drawReads()`): "(When empty, writes "text")". Run view: `runValue(thread, fallback)` shows the fallback for an empty value; `runFilled()` uses each place's own.
- External API: the nodes description of POST /workspace/flows (api.php) states the syntax.
- Tests: `workspace_flow_fill_test.php`, `workspace_flow_graph_check_test.php`, `workspace_flow_webhook_url_test.php`, `workspace_flows_canvas_test.php` (`…_value_token_fallback`), templates and draft card threads.

## What else did #2415 decide?
- 1a: value boxes are worked out when a thread reads them, cached per run, no run row (`workspace-flows-value-boxes.md`). 3a: the visual designer will reuse the chip and thread component through the graph canvas core when that plan starts; nothing built for it.
