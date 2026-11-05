---
name: workspace-flows-derived-calculate
description: Calculate rule of a flow's derived value box (#7016, FA1): one calculator formula, values bound by the resolver not pasted, places, empty on error, the check, the canvas function list
---

## What does it do?
- Rule `calculate` of `ws_flow_derived_rules()` (type number, one input `a`, `places` 0-6). `a` is one expression of the workspace calculator (`ws_calc_evaluate()`): + - * / ^, brackets, a percent (18%), comparisons (1 or 0), the functions of `ws_calc_functions()` (English or Turkish names, arguments split by ;). Decided in #7016 (`workflow/decisions-made-product-32.md`).
- The result is rounded to `places` and written by `ws_flow_derived_number()`. Places default to 0 as for round: the canvas shows the picker for both rules.

## How are the values read?
- `ws_flow_derived_bind()` puts each `{{node.*}}` / `{{trigger.*}}` reference as a calculator name `@L<n>`; `ws_flow_derived_calculate()` reads each value with `ws_step_value_of()` (a `|fallback` too) and `ws_calc_number()` (1.250,50, 18% as 0.18, 450 ₺), and the resolver hands the number for that slot. The value is never pasted into the formula, so "2 + 3" or "A1" in a value is no number, not an operator.
- A formula holding `@` by hand is unreadable (a slot is never written by hand); a cell (`A1`) or an unknown word resolves to `ref`.
- An empty or unreadable value, a calculator error (division by zero, syntax) or a non-finite result gives '' like the other rules; `IFERROR()` can step around an error.
- `ws_flow_pure_values()` hands calculate the unfilled `a` (date words filled first) and the known values; the other rules still get `ws_flow_derived_fill()` text. `ws_flow_derived_value()` takes the values as a 4th argument for calculate.

## What does the check say?
- `ws_flow_derived_formula_reads()`: the formula tried with 1 for every value; only `syntax`, `name` or `ref` errors are a problem ("has a formula the calculator cannot read"), not a division that may be by 0.

## And on the canvas?
- `derivedFields()` labels `a` "Formula" and adds `derivedFunctions()`: a hint and the calculator's functions from `FL.derived.functions` (`ws_flow_derived_lists()`), each name local with its help on hover. The list comes from `ws_calc_function_help()` (calc.php), which the writing windows' `ws_calc_js_config()` reads too.
- The AI flow draft's `value.a` hint (`flow_draft.php`) describes the formula.

## Tests?
- `tests/workspace_flow_derived_calculate_test.php`; the rule list in `tests/workspace_flow_draft_test.php`.
