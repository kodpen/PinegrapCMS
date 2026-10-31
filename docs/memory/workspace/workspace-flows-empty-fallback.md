---
name: workspace-flows-empty-fallback
description: The per-thread "When empty, write" fallback of a Flows value (T6, PR #3729, #2415 2a): token syntax, where it is filled, which references take it, the warning rule, the chip
---

## How is a fallback written?
- In the token itself, so threads still come only from the fields: `{{node.<id>.<key>|<text>}}` (also `{{trigger.<key>|<text>}}`). Group 3 of `WS_STEP_VALUE_PATTERN`, group 4 of JS `VALUE_TOKEN`.
- In the text `\`, `{`, `}` and `|` are written `\\`, `\{`, `\}`, `\|`, so `}}` never ends it early; one line. `ws_step_value_token($box, $key, $fallback)` and JS `tokenWith()` write it (trimmed, cut to `WS_STEP_FALLBACK_LENGTH` = 100 characters; empty means none); `ws_step_value_fallback($match)` and `tokenFallback()` read it back; `tokenBare()` drops it. The pattern's limit is 400 bytes (PHP reads bytes).
- Per thread (decision 2a of #2415): each place a value is written has its own fallback.

## When is it used?
- `ws_step_value_of($match, $values)`: the value as text, the fallback when the run gives none or an empty one (skipped, not run, empty output).
- `ws_step_fill_nodes()`: then escaped for its target like any value (`ws_step_fill_value()`, D3), so a tag or `{{date}}` in it stays text.
- `ws_step_url_fill()`: the fallback obeys the address rule (#3410): digits only, else the run refuses it; `ws_flow_reference_problems()` refuses a non-digit fallback in a `urls` key at save.
- `ws_flow_derived_fill()`: a derived box's input takes it as it is (the reader escapes).
- `ws_step_fill_missing()` (the box try's "empty" list) leaves out a reference with a fallback.

## Which references take none?
- A condition's `rule.ref` (its normalizer keeps only an exact `{{…}}`), a loop's list and an End box's gathered value (`ws_step_value_single()`: one reference, no fallback, else `bad_config`) read a value as it is.
- The canvas offers none there (#4103, issue #3979): `fieldFallback(node, key)` is false for For each `list` and End `collect`; `valueField(…, takes, bare)` passes `bare` to `valueInput()`/`valueChip()`, so a click opens no "When empty, write…" and the chip's title has no `fl_values_empty_tap`. Before, it did, and publish failed without naming the fallback.
- One carrying a fallback anyway (API, old draft) gets its own `bad_config` text (`ws_step_value_has_fallback()`, `ws_flow_loop_list_problem()`): "The list of X cannot have a “When empty, write” text…" / "The value X gathers cannot have…".

## What about the warning?
- `skippable_reference` is raised only when one place in the box's texts reads the value without a fallback (`$bare` in `ws_flow_reference_problems()`); references are named without their fallback. A fallback never excuses `bad_reference`. The derived-box warning is unchanged.

## What does the canvas do?
- `dataThreads()` keys a thread by the bare token (`thread.token`) with `thread.fallback` (the first place's); from, to and the dot stay as without one. `bindTargets()` uses `holdsToken()`.
- The chip (`valueChip()`) reads "Box › Value, else "text"", dashed only while skipped without a fallback; clicking a known chip in a draft opens "When empty, write…" (`askFallback()`), which rewrites its token; an empty answer clears it.
- Outline: "(When empty, writes "text")". Run view: `runValue(thread, fallback)` shows the fallback for an empty value, never for an unknown one (a loop item value that cannot be read, `workspace-flows-for-each-canvas.md`); `runFilled()` uses each place's own.
- API: the nodes description of POST /workspace/flows states the syntax.
- Tests: `workspace_flow_fill_test.php`, `…_graph_check_test.php`, `…_webhook_url_test.php`, `workspace_flows_canvas_test.php` (`…_value_token_fallback`, `…_loop_fields_take_no_fallback`).

## What else did #2415 decide?
- 1a: value boxes are worked out when read, cached per run, no run row (`workspace-flows-value-boxes.md`). 3a: the designer will reuse the chip and thread component via the graph canvas core; nothing built yet.
