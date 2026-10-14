---
name: settings-card-layout-pitfalls
description: CSS pitfalls of the settings card row layout: no nested :has(), specificity of :has() rules, tagin and form-label predicates, divider alignment with text.
---

## What are the :has() specificity traps?
- `:has()` inside `:has()` CANNOT be written: "a row containing a transformed cell" asks exactly that; the selector becomes invalid and the rule drops without a word. The vertical rhythm is therefore written with the gutter variable, not a predicate. Symptom: "I fixed it but nothing changed".
- Never cancel a `:has()` rule with an ordinary selector. Fell into it twice: `:last-child` (0,4,0) could not beat the border rule (0,5,1) and never applied. No rule that does not carry the same predicate can override it.
- Placement rules must be MORE specific than the catcher. The `> *` rule that drops an unexpected child to a full row carries the specificity of the `:has()` argument; `grid-area` rules without the same predicate lose to `grid-column`, and the control lands on top of the description (happened in the Sensitivity field). `:has()` specificity equals its most specific argument; do not write a short predicate.

## Which markup classes does the predicate depend on?
- A tag field is not an ordinary control (`input.form-control:not(.tagin)`): the real control of `tagin` is `div.tagin-wrapper`; the `input.form-control` next to it is a hidden store, and the predicate used to see it and wrongly transform the seven firewall cells.
- A field's label is `form-label`. 31 places wrote `form-check-label` (mostly payment-provider blocks); the predicate does not know it and those rows stayed in the old layout alone. That class belongs to a checkbox's label, never to a field's.

## Where must the divider line start and end?
- At the card's content edge, where the text starts. `.row` has a negative horizontal margin and gives it back as cell padding, so a border drawn on the cell is half a gutter WIDER than the text inside: the line overruns the text at both ends and the card looks crooked (measured: line 305, text 317).
- On a transformed cell padding is handed over to margin: `padding-inline: 0` + `margin-inline: gutter/2`, width `calc(100% - gutter)`. Text does not move; the line starts and ends exactly where the text does.

Related: docs/memory/ui/settings-card-row-layout.md
