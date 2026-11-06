---
name: workspace-flows-ai-box
description: The Pinegrap AI box of flows (kind ai, B2): modes, settings, classify's named outcomes as arrows, what is sent, the wait on its own step, the call after the response, the hourly look and the deadline
---

## What is it?
- Step kind `ai` ("Ask Pinegrap AI") in `ws_step_kinds()`, functions in `includes/workspace/flow_ai_box.php` (prefix `ws_step_ai_`; `ws_flow_ai_*` is the flow-drafting AI). Decision #7110 (`workflow/decisions-made-product-33.md`). Tests `tests/workspace_flow_ai_box_test.php`.
- Settings (`ws_step_ai_normalize()`): `mode` write | summarize | extract | classify (unknown → write), `prompt` (4,000), `text` (20,000), `classes` (up to 8 names of 60 characters, once each in any case, never `other`), `closes_in` hours (default 24, 1-720). Both texts take values.
- Publish problems: write needs the prompt; summarize and classify the text; extract both; classify its classes. `ws_step_ai_check()` also refuses while Pinegrap AI is not set up (`ws_ai_ready()`); a hold is no refusal.

## Which arrows?
- Kind outcomes: `done`, `class_1`…`class_8`, `other`, `failed`. Per box (`ws_flow_node_arrow_outcomes()` in `ws_flow_graph_check()`): write/summarize/extract done, failed; classify `class_N` for each name by its place (renaming keeps the arrow), `other`, failed. Plus `always`.
- A named outcome or `other` with no arrow ends that branch (as an approval's approved).

## What goes out?
- `ws_step_ai_messages()`: a `writer` system prompt (listed in tests/ai_prompt_rules_test.php) with the mode's task, the site's language for write and summarize; the user message is the filled prompt, then the filled text as `pg_ai_data_block('text', …)`. Nothing else of the run, record or site is sent.
- Through `pg_ai_chat()` only (site quota, hold, licence bookkeeping), temperature 0.5 for write, 0.1 otherwise, 2,000 tokens.

## How does it read the answer? (`ws_step_ai_parse()`, pure)
- write, summarize: the words, cut to 8,000 → `answer`.
- extract: JSON `{found, value, number}` only; `number` from the model, else the value's first figure through `ws_calc_number()` (a minus only where it starts a word: PG-1042 → 1042) → `answer`, `number` (decimals kept as text), `found` 1/0. No JSON reads as no answer.
- classify: `{"class": …}` or a bare name, any case → `class_N`, `answer` the name; anything else → `other`.

## How does it wait?
- `ws_step_run()` fills the texts, then `ws_step_ai_wait()`: one conditional UPDATE from running to waiting, `wait_key`/`effect` `ai:<own step id>`, no `wait_event`, `wake_at` = next look (an hour, never past the deadline), the filled prompt and text kept in `result.data`; announced as waiting.
- `ws_step_ai_queue()` registers `ws_step_ai_flush()` at shutdown: `fastcgi_finish_request()`, `ignore_user_abort()`, at most 3 calls a request; `ws_step_ai_call()` settles only from `waiting` with its own key (done, the outcome, the declared values; the kept prompt is dropped).
- A failed call or unreadable answer stays waiting, its sentence saying why. `ws_flow_step_recheck()` → `ws_step_ai_recheck()`: run ended → skipped; past `claimed_at + closes_in` → failed path (`ws_step_ai_timeout_settlement()`: done/failed where an arrow leaves on failed, else the box fails), with the last sentence; else queued again.

## Who reads what?
- `ws_step_outputs_held()['ai'] = *`; non-editors read "Pinegrap AI answered." / "Pinegrap AI gave no answer." (`ws_step_result_shown()`). Not a role-3 member kind (`ws_flow_member_kinds()`): it spends the site's quota.
- Try: "Asks Pinegrap AI to …"; nothing is sent.

## Canvas?
- `workspace-flows-ai-box-canvas.md`.

## What is unverified?
- A live call on a site with a key, and a browser look at the form; the pure parts, the wiring and the canvas source are under tests.
