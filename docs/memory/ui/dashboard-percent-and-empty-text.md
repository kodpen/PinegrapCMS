---
name: dashboard-percent-and-empty-text
description: Dashboard wording: '{var:1}%' percentage figures and the whole-sentence empty states of each card
---

## How are percentages printed on the dashboard?
- Through `lang(array('string' => '{var:1}%', 'vars' => …))`, which Turkish words as `%12`. Signs and arrows stay outside the template (`+%12`, `▲ %12`). The Visitor Summaries period switch takes the template from PHP as `T.percent` with a `{n}` placeholder. The System Status score puts its small `<span>%</span>` on the side the translated template puts it.
- The Visitor Summaries period cells carry `aria-pressed`, and the switch sets it beside `.is-active`.

## Which empty states changed?
- No sentence is "There is no {var:1} right now." with a noun dropped in any more: each card has a whole sentence (No orders yet., No open shopping carts., Nobody is online right now., No sales yet., Every product is in stock., No offers right now., No other currencies are set up.).
- Exchange rates shows its empty state when no row was printed (a base-only site printed none). Subscriptions shows one when all four licence rows are empty. Activity with no rows at all says "No activity recorded yet." instead of the per-kind note.

Related: docs/memory/ui/dashboard-keyboard.md, docs/memory/ui/dashboard-card-figures.md
