---
name: widget-loop-position
description: Row position tokens of every widget list (__loop_index, __loop_count, first/last/odd/even): row marks only when used, filled by the finish pass per printed page, what counts as a row.
---

## Which tokens are there, and what values do they take?
- `__loop_index` (row number, from 1), `__loop_count` (rows printed), `__loop_is_first`, `__loop_is_last`, `__loop_is_odd`, `__loop_is_even` (`'1'` or `''`). Map: `pg_sw_finish_position_values($i, $n)` in `includes/fn/widgets_finish.php`; the editor's `SW_LOOP_POSITION_TOKEN_GROUPS` lists the same six (`tests/widget_loop_position_test.php`).
- A binding value like any token: text, href, src, alt, value, and `show_if` ([[widget-conditions]]). `multiple_of 3` on `__loop_index` is "every third row"; `empty` on `__loop_is_last` drops a divider after the last row. No renderer fills them and `_apply_bindings()` is not edited.

## When are rows marked?
- `_split_widget_tree()` puts `<!--pg-row:NONCE-->` before and `<!--/pg-row:NONCE-->` after the loop's children (custom_html nodes, as the ghost marks) only inside a widget render, only when the JSON of the children names one of the six (`pg_sw_finish_uses_positions()`), never around an empty loop. Any other tree splits as before.
- The mark is the row's first child, a leading comment: the edit pencil and a comment's id still land on the row's first element.

## How does the pass fill them?
- Stage 1 of [[widget-finish-pass]], before the conditions, so a carrier holding `^^__loop_is_first^^` is filled before its span is judged. Top-level row spans are found with `strpos()` and `_pg_sw_finish_pair_end()`, counted, and each body's tokens (format suffix dropped) take that span's values; spans inside a span number among themselves first.
- Then every row mark and position token left goes: one bound outside the loop reads as empty. Output with no nonce but a position token still runs this stage.

## Are positions per page?
- Per printed page: page 2 starts at 1, `__loop_count` is the rows on this page. `__result_number` (search results) stays the number across pages.

## What counts as a row?
- Every copy of the row template the widget prints: catalog group cards and product cards count together, groups first; comments count replies among the rows; express order's item rows count across recipients. A template a renderer prints once (a not-found state) is one row of one.
- A widget nested in a row has its own scope and rows. The canvas ghosts come from a Preview render with as many rows as fetched, so their count is the ghost count.
- Not covered: express order's `recipient_loop_area` (it has `__recipient_index`).

## pg-hide-if-empty or show_if?
- Renderers judge pg-hide-if-empty before the pass and see `^^__loop_is_first^^` as text, so an element holding only that token is never dropped by the class. Use `show_if __loop_is_first` (not_empty).

## Why does the sweep keep them only in a scope?
- `pg_sw_sweep_tokens()` keeps the six (with a suffix) while `pg_sw_finish_active()`; the pass fills or removes them. Outside a scope (RSS parts, anything drawn without the dispatcher) no pass runs, so they are swept like any token.
- One pattern with two branches (`(position token)|any token` → `$1`): a negative lookahead let a kept token's closing carets pair with the next token's opening ones.

## Where does the editor offer them?
- `_swBindTokenOptions()` appends the group "Position in the list" when `_swInMainLoop(node)`: the first parent of type loop_area, not past a recipient_loop_area or the widget root; not on the loop_area itself. `_swBindLabel()` names them in every kind. Bound from the code view (`{{__loop_index}}`) until W4 adds the Bind Data row.
