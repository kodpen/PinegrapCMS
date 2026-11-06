---
name: workspace-flows-derived-text
description: Text rules of a flow's derived value box (flows-next B3): replace, extract a number or an e-mail address, capitals, small letters, tidy spaces, cut to N, split and take part N; the third input c
---

## Which rules?
- In `ws_flow_derived_rules()`, worked out by `ws_flow_derived_text()` (flow_values.php), as Zapier Formatter does. All text, but `extract_number` (number).
- `replace`: every `b` in `a` as written (never a pattern, case kept) becomes `c`; an empty `c` takes it out. No `b` gives ''.
- `extract_number`: the first figure of `a` read by `ws_flow_typed_number()` in the site's way (1.250,50 on a Turkish site); a dash right after a letter or digit (ABC-123) is no minus.
- `extract_email`: the first address with a dotted domain (user@localhost is none).
- `upper` / `lower`: `ws_flow_derived_case()`, Turkish i/İ, ı/I pairs when the site's language is `tr`.
- `trim`: runs of spaces, tabs and line ends become one space.
- `cut`: the first `b` characters (`mb_substr`), `b` a whole number 1-9999; no ellipsis.
- `split`: `a` split at `b` (spaces when `b` is empty: the inputs are trimmed, so a space cannot be typed), part `c` trimmed: 1 when empty, -1 the last, -2 the one before; no such part gives ''.

## What is the third input?
- `c` in `ws_flow_derived_normalize()`, read by `ws_flow_derived_three_inputs()` rules (replace, split). Read for references by `ws_flow_derived_reads()` and the graph check (flows.php), filled by `ws_flow_pure_values()` and passed as the 5th argument of `ws_flow_derived_value()`.
- A typed space in `c` is trimmed too: replace cannot put a single space in.

## What does the check say?
- replace without `b`; cut with a written `b` that is not a whole number; split with a written `c` that is not a whole number other than 0. A value in them is read at run time.

## And on the canvas?
- `derivedRule()` carries `three`; `derivedFields()` names `b` per rule (Find, Number of characters, Split at) and draws `c` (Replace with, Part to take). `VALUE_FIELDS.value` has `c`, so threads can drop into it.
- AI drafts: `value.b` and `value.c` hints (flow_draft.php), Claude's flow text (claude.php) lists the rules with b.

## Tests?
- `tests/workspace_flow_derived_text_test.php`.
