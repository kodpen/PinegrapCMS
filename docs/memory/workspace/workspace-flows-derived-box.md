---
name: workspace-flows-derived-box
description: The derived value box of a flow (kind value, T3, PR #2701): its allowlisted rules, inputs and value, how a run works it out, the transitive "comes before" check, the canvas form
---

## What is it?
- Kind `value` in `ws_flow_kinds()`, `pure` like constants (no arrows, outcomes or run row: workspace-flows-value-boxes.md). It gives one value, `{{node.<id>.value}}`, worked out from one or two inputs by a rule.
- `ws_flow_derived_normalize()`: `rule`, `a`, `b` (one line of 500, may hold `{{node.*}}` and `{{trigger.*}}`), `op`, `places` (0-6), `format`. An unknown rule is kept as join.

## Which rules?
- `ws_flow_derived_rules()` (rule => type): compare (number: 1 when it holds, else 0; `op` of `ws_flow_derived_operators()`, as a value condition compares: texts without case, numbers by value, gt/gte/lt/lte only between numbers), join (text: a and b with a space, trimmed), add, subtract (number), round (number, `places`), date_format (text, `format` of `ws_flow_derived_formats()`), add_days (text, b a whole number, at most 3650).
- Numbers are written by `ws_flow_derived_number()` (no trailing zeros, six places at most). Dates are read by `ws_flow_derived_date()`: `Y-m-d`, with `H:i(:s)`, or a Unix time in the site's time zone; words such as "next monday" are not dates (no `strtotime()`). Computed with `gmmktime()`/`gmdate()` on the wall-clock parts.
- An input the rule cannot read gives an empty value, never an error.
- Record field was dropped from the plan's list: it reads the database, so the box would not be pure.

## How does a run work it out?
- `ws_flow_pure_values($graph, $values)`: the inputs filled raw by `ws_flow_derived_fill()` (the reader's own fill makes the value safe where it lands), from the run's values, the constants and derived boxes already worked out (passes in dependency order, any box order). A box in a loop of derived boxes gives ''.
- Worked out each time a reader runs; no cache, no row.

## How long can a derived value be?
- At most `WS_FLOW_DERIVED_VALUE_LENGTH` (4000) characters (#3367). `ws_flow_derived_value()` cuts both inputs and the join there; `ws_flow_derived_fill()` gives each placed value only the room left, then cuts the input there. `mb_substr()`: never mid-UTF-8.
- Why: one input may cite a value many times (about 58-fold per join), so three chained joins exhausted `memory_limit` in `ws_tick` or a job: the box stayed `running`, the rest of the tick was skipped. Role-3 members build these boxes (#3345).
- Why 4000: the longest text a message box keeps (`ws_flow_lines(..., 4000)`), so the common landing place gets it whole.
- Later: a filled text is not cut where it lands (mail body, webhook, MCP arguments, table cell); the check does not warn about repeated citations.

## What does the check say?
- `ws_flow_derived_problems()`: no `a`; no `b` for a rule of two inputs (`ws_flow_derived_two_inputs()`); add_days with a written b that is not a whole number. `ws_flow_derived_loops()`: a box reading itself through derived boxes (`bad_config`).
- The box's own references are checked for the box and the field only, never "does not come before" nor skippable: it has no place among the arrows.
- A box reading it is checked against what it reads, at any depth through derived boxes (`ws_flow_derived_sources()`): a box not before the reader is `bad_reference`, one that may be skipped `skippable_reference` (warning), both naming the derived box.

## And on the canvas?
- `KINDS.value` (`bi-calculator`, dashed border like constants), palette entry. `derivedFields()`: rule, "Value it works from" and the second value as value fields (chips, threads), comparison, decimal places, date shape. `derivedRule()` reads `FL.derived` (`ws_flow_derived_lists()`).
- `valueGroups()` of a derived box offers every other box, none marked skipped; `boxValues()` gives `value` of its rule's type; `openFields()` drops b for a one-input rule.
- Tests: `tests/workspace_flow_values_test.php`, `test_workspace_flows_canvas_derived_box_reads_any_box`.
