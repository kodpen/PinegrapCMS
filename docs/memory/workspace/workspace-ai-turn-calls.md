---
name: workspace-ai-turn-calls
description: How ws_ai_step() makes one model turn's tool calls: answer, fail and decide last; a failed canvas, live page or page tool holds the turn's answer back; why
---

## In what order are one turn's tool calls made?
- One model turn can hold several calls (the AI bridge reads the model's XML tool calls itself; since 2026-11-26 it drops exact duplicates and caps a turn at 20).
- `ws_ai_turn_order()` (`includes/workspace/ai.php`): the calls that end the request, `answer`, `fail` and `decide`, are made after the turn's other calls; each group keeps the written order, so an answer written before a fail still wins.
- The assistant message saved in the conversation lists the calls in that new order, so every tool result follows its own call: the bridge writes results as `[tool result for <name>]` in order, without ids.

## What holds a turn's answer back?
- `ws_ai_holds_answer($name, $result)`: a `page_*` tool, a canvas tool (`draw_canvas`, `read_canvas`, `edit_canvas`) or a live page tool (`make_live_page`, `read_live_page`, `edit_live_page`) that answered with an error, or whose arguments were not valid JSON. A `page_*` tool's warning holds it back too; a canvas tool's does not.
- A held answer is not posted: its tool result tells the model a tool above failed, and the model fixes it and answers in a later step.
- Only while `$steps < WS_AI_MAX_STEPS - 2`: on the last steps the answer goes out as written, so a request still ends.

## Why?
- dev.pinegrap.com, 2026-11-26: "bir çizim üret kalp şeklinde kırmızı" was answered "İşte kalp şeklindeki kırmızı çizim:" with no canvas under it. One turn held `draw_canvas` twice and `answer` twice; the first `answer` ended the request and the calls after it were dropped, and a failed `draw_canvas` held nothing back (only `page_*` tools did).
- The saved request (ws_ai_requests 3200) kept no state after it was answered at step 0 and the site logged no PHP error, so whether the drawings came after the answer or failed is not known.

## Tests?
- `tests/workspace_ai_turn_test.php`: the order and the hold-back. The loop itself needs a database and a model; not run live.
