---
name: widget-condition-rows
description: Several value conditions on one widget node (W25): show_if_2/_3 beside show_if, the All/Any join, the marker with several tests, translation per row, the twin, the panel rows.
---

## How are several conditions stored?
- Row 1 is `_bindings.show_if` + `_bindParams.show_if {op, value}` as before; rows 2 and 3 are `show_if_2` and `show_if_3` in the same two maps (`pg_sw_cond_names()`, JS `SD_COND_NAMES`).
- The join is `_bindParams.show_if.join`: `any` (one is enough) or `all` (missing or empty). Owner decision 2026-10-27 (option c): up to three; mixed AND/OR uses nested elements. Reason: classic layouts' OR ties two different values (city or postcode; offer or offer discount).
- No new object prop: `pg_designer_tree_objects()` needs nothing.

## How are they read?
- `pg_sw_cond_specs($props)`: null without one; false (draw nothing) when a row is invalid, a later row stands without row 1, or the join is unknown; else `{join, conds}`. An empty middle row is skipped. `pg_sw_cond_spec($props, $name)` reads one row.
- `pg_sw_cond_present()` is the renderer's gate (any name, set or empty); `_render_tree_node()` drops all three names from the node.
- `pg_sw_cond_eval_all($join, $tests)` joins; twin `_sdCondEvalAll()`, vectors in `tests/fixtures/widget_condition_join_vectors.json`. `_sdCondSpecs()` twins `pg_sw_cond_specs()`.

## What does the finish pass write?
- One condition: the markers as before, byte for byte. Several: `<!--pg-if:NONCE:N:op:b64.op:b64:any-->`, carriers `N`, `N.2`, `N.3` before the close marker ([[widget-finish-pass]]).

## What else reads every row?
- `pg_sw_cond_tree_json()` drops all three. `_sdPartPlaced()` too.
- Translation: one field per row, `_cond:show_if`, `_cond:show_if_2`, `_cond:show_if_3` (`pg_sw_cond_operand_is_words($props, $name)`).
- `_civ_related_list_shown_when_off()`: with `all` one failing `__has_related` row cuts; with `any` every row must be one and fail.
- Canvas: `_sdCondCanvasShows()` joins the rows; `_sdCondTreeTokens()` reads all three for the band states.

## What does the panel show?
- `_sdConditionRows()`: one block per row (token, operator, compared text, `data-cond-row`), "Add a condition" opens an empty row (`_sdCondOpenRow`, written once a value is chosen), a later row's empty choice is "Remove this condition". With two or more rows the "All must hold / Any is enough" select (`.sd-sw-cond-join`).
- `_sdCondWrite(node, token, op, value, row)` writes one row; `_sdCondWriteRows()` writes all, compacted (a removed row lets the next move up; one row keeps no join). `_sdCondRowsOf()`, `_sdCondJoin()` read them.
- Chip: "Only when: …" then "and …" / "or …" per further row. The Translate section labels rows 2 and 3 "Condition 2: compared with".
- A required control: no add button, as before no condition offered.
