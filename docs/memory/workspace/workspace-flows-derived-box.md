---
name: workspace-flows-derived-box
description: The derived value box of a flow (kind value, T3, PR #2701): its allowlisted rules, inputs and value, how a run works it out, the transitive "comes before" check, the canvas form
---

## What is it?
- Kind `value` in `ws_flow_kinds()`, `pure` like constants (no arrows, outcomes or run row: workspace-flows-value-boxes.md). It gives one value, `{{node.<id>.value}}`, worked out from one or two inputs by a rule.
- `ws_flow_derived_normalize()`: `rule`, `a`, `b` (one line of 500, may hold `{{node.*}}` and `{{trigger.*}}`), `op`, `places` (0-6), `format`. An unknown rule is kept as join.

## Which rules?
- `ws_flow_derived_rules()`: compare (number: 1 when it holds, else 0; `op` of `ws_flow_derived_operators()`, as a value condition: `ws_fold()` texts, gt/gte/lt/lte only numbers), join (text: a and b with a space, trimmed), add, subtract (number), round (number, `places`), date_format (text, `format` of `ws_flow_derived_formats()`), add_days (text, b a whole number, at most 3650).
- Numbers by `ws_flow_derived_number()` (no trailing zeros, six places at most). Dates by `ws_flow_derived_date()`: `Y-m-d`, with `H:i(:s)`, or a Unix time in the site's zone; no free words (no `strtotime()`), only the date words below; `gmmktime()`/`gmdate()` on the wall-clock parts.
- An input the rule cannot read gives an empty value, never an error.
- No record field: it would read the database.

## How does a run work it out?
- `ws_flow_pure_values($graph, $values)`: the inputs filled raw by `ws_flow_derived_fill()` (the reader's fill makes it safe), from the run's values, the constants and derived boxes already worked out (passes in dependency order). A derived loop gives ''.
- Date words: `{{date}}` (`Y-m-d`), `{{time}}` (`H:i`), `{{weekday}}` in typed `a`/`b` are `$now` (3rd argument, `time()` by default) in the site's zone (`ws_flow_derived_words()`), filled before the values, so a value holding a word stays. Not `d.m.Y` as scheduled texts: the date rules read them. Why: a counter line in one flow (`workflow/decisions-made-product-30.md`). Named in `fl_values_hint_value`, draft hint `value.a`.
- Worked out each time a reader runs; no cache, no row.

## How long can a derived value be?
- At most `WS_FLOW_DERIVED_VALUE_LENGTH` (4000) characters (#3367): `ws_flow_derived_value()` cuts inputs and join, `ws_flow_derived_fill()` gives each placed value the room left (`mb_substr()`).
- Why: chained joins multiplied a value and exhausted `memory_limit` in `ws_tick` (#3345). 4000 is a message box's longest text.
- Landing (#3754): mail, digest, webhook, summary title cut by `ws_scheduled_fill_cut()`.

## What does the check say?
- `ws_flow_derived_problems()`: no `a`; no `b` for a rule of two inputs (`ws_flow_derived_two_inputs()`); add_days with a written b that is not a whole number. `ws_flow_derived_loops()`: a box reading itself through derived boxes (`bad_config`).
- Its own references: box and field only (it has no place among the arrows).
- A box reading it is checked against what it reads, at any depth through derived boxes (`ws_flow_derived_sources()`): a box not before the reader is `bad_reference`, one that may be skipped `skippable_reference` (warning), both naming the derived box.

## And on the canvas?
- `KINDS.value` (`bi-calculator`, dashed), palette entry. `derivedFields()`: rule, the two values as value fields, comparison, places, date shape. `derivedRule()` reads `FL.derived` (`ws_flow_derived_lists()`).
- `valueGroups()` of a derived box resolves every other box (chips, threads), none skipped. "Add value" and a dropped thread offer only `derivedOffer()`'s (#6447): boxes before every reader (through derived boxes too), starts and pure boxes, never a derived box reading it; any while unread.
- `boxValues()` gives `value` of its rule's type; `openFields()` drops b for a one-input rule. A chip wraps in its field (#6447).
- Tests: `tests/workspace_flow_values_test.php`, `test_workspace_flows_canvas_derived_box_reads_any_box`, `tests/workspace_flow_derived_offer_test.php`.
