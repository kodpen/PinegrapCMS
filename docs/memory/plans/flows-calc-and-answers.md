---
name: flows-calc-and-answers
description: Plan (2026-11-05): the Calculate rule of the derived value box (#7016), then an answer from the people an approval box asks (#7015); claim as [plan:flows-calc-and-answers] FA<n>
---

## What is this plan?
- The production-line flow the owner asked for on dev: a flow asks a channel, reads a number from the answer, calculates with it and writes a row. #7016 (decided) gives the calculation; #7015 (owner question open) gives the answer. Decisions: `workflow/decisions-made-product-32.md`.

## Which steps are there?
- FA1 (S) free. Calculate rule: `calculate` in `ws_flow_derived_rules()` (type number, one input `a`, `places`); `ws_flow_derived_value()` reads each `{{node.*}}` / `{{trigger.*}}` value with `ws_calc_number()` and binds it through `ws_calc_evaluate($formula, $resolve)`, never pasted into the text; an unreadable value or a calculator error gives ''; the canvas shows the rule and the calculator's function list; a pure test in `tests/` beside `workspace_flow_derived_offer_test.php`; memory `workspace/workspace-flows-derived-box.md`.

- The second step (the answer on the approval box) is added here by the architect with the owner's answer to #7015.

## Which decisions bind it?
- `workflow/decisions-made-product-32.md` (#7016); D9's loop limits and the data-passing rules (a filled value is never read again as a placeholder, `workspace/workspace-flows-decisions.md`); the date words and the pure-box rule of `decisions-made-product-30.md`.

## Where does it start?
- FA1 now.
