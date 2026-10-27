---
name: widget-condition-canvas
description: The canvas state preview of value conditions (W19, #1904): band chips, states, which value a token reads, picking a hidden node, the _sdCondEval twin, list and main loop sample rows (#1914, #1928)
---

## What does the canvas draw for a value condition?
- One state at a time (`_sdCondCanvasState`, editor only, one for every widget). A widget whose tree holds a `show_if` gets chips on its band (`_sdCondCanvasStates(tree)`, `_sdStateChips()`): its sample values, every value empty, every element. Without a condition no chips, and every element is drawn.
- A code token's values are states of their own, in place of the sample values (`_sdCondCodeStates()`): `__order_status_stage` gives the six stages, so the order view's status badges draw one at a time. `__step_kind` is a list row's and stays the row sample's (`created`).
- A node its condition fails in the state takes `sd-state-off` (hidden), as the comments and login states hide; its chip "Only when: …" stays on every drawn node.

## Which value does a token read?
- `_sdCondCanvasValue(token, node, state)`: empty in the empty state; a code token its state's value (the first one in another state); a flag of `_SD_FLAG_TOKENS` `'1'` (the sample previews flags empty); else `_displayValueForBinding('text', …)` read as text (`_sdCondText()`), so a comment sample or a list row sample reads its own value.
- An invalid condition (refused token, unknown operator, operator without its value) draws only in "All elements", as the page draws it nowhere; one on a required control is ignored, as on the page.

## What happens to a node picked in the layer tree?
- `_sdCondStateFor(root, node)`: the state drawn if it draws the node and every node above it, else the first that does, else "All elements". The band sets it before drawing; `_sdSelectionNeedsCanvas()` redraws when it differs. A chip whose state hides the selection moves the selection to the widget (`_sdSetCondCanvasState()`).

## How does the editor test a condition?
- `_sdCondEval(op, value, operand, decimal)` and `_sdCondPlain`, `_sdCondText`, `_sdCondFold`, `_sdCondFigureOk`, `_sdCondFigureNumber`, `_sdCondAmount`, `_sdCondNumber`, `_sdCondComparable`, `_sdCondIsWhole`: line-for-line twins of the `pg_sw_cond_*` family ([[widget-conditions]]). White space is the written-out set (`SD_COND_WS`), not `\s`.
- One separator with three digits after it follows `decimal`: the site's (`software_money_format.decimal`), else `.`.
- `tests/widget_condition_eval_test.php` runs under Node: the shared vectors with both decimal points, a grid of about 8,000 cases against `pg_sw_cond_eval()`, the three-digit case, and the canvas states. A change to either side changes both.

## What do a list's sample rows show? (#1914)
- After a list's card (a `loop_area` with `props.source`) the canvas draws sample rows from the same nodes (`_sdListGhostEls()`, both the div and the table loop): ghost DOM as the sample comments, off with the sample switch, none when the band is amber, none inside a sample row, at most `props.limit` less one.
- `SD_LIST_SAMPLE_ROWS` gives each source's rows the values that differ from the card (`order.steps`: paid, shipped, delivered as an estimate, so each timeline icon shows in its row); another source gets two rows of the card's values. `_sdListSample` is read first in `_displayValueForBinding()` after a comment sample; a test that cuts it declares it.

## Which main loops get sample rows? (#1928)
- The widget's first loop without a source (`_sdFirstLoopArea()`), two rows of the card's values, in a kind that repeats it and has neither server ghosts (`_SD_GHOST_TYPES`, [[designer-widget-ghosts-and-edit-chips]]) nor sample comments: the cart, the order view's items, an account's order list. None in a single-record kind (`_sdLoopIsSingle()`, the rule that also hides the loop's band), inside a sample row or sample comment.

## Which state does the canvas open on?
- Owner (2026-10-27): the first state ("Completed" in the order view). Today the sample; W19 follow-up.
