---
name: look-figure-cards
description: Look A's figure cards (SH4, #9725): .pg-figures, .pg-figure, -label, -value, -note, the strip .pg-figure-strip; Orders, Commissions, Edit Invoice Settlement, Receipt, Marketplace Products hero
---

## What is the recipe?
- Block "Look A: figure cards" in `backend.src.css`, after the report screens' block. It copies the report figure (`.pg-report-fig-*`, `look-report-screens.md`) value for value; `tests/look_figure_cards_test.php` keeps the two equal.
- `.pg-figures`: the grid, four a row (`--pg-figures`), auto-fit under 992 px, two a row on a phone with an odd last card the whole row.
- `.pg-card.pg-figure`: one card (padding 15 / 18 / 14). `.pg-figure-label` 13 px regular second text; `.pg-figure-value` 26 px (`--pg-text-figure`, 21 on a phone `--pg-text-figure-sm`), weight 650, Display cut, tabular; `> small` the unit; `.pg-figure-note` 12 px third text.
- Value inks: `.is-good` success emphasis, `.is-bad` danger emphasis. A value may keep a `fg-*` utility of its screen (Commissions, Edit Invoice) for its meaning.
- The label, value and note also stand alone, without a card: a value first in its block loses its top margin (a hero's count above its label).
- The strip form `.pg-figure-strip`: figures as cells of one card divided by rules (`.pg-figure-strip-cell`), the value at `--pg-text-figure-sm` with what stands at its right in `.pg-figure-strip-line`, a last cell `.pg-figure-strip-end` for a button and a full row `.pg-figure-strip-more` for what it opens; two cells a row under 992 px.

## Which screens use it?
- Orders (`view_orders.php`, its `$strip_cell`): the four figures as cells of one strip card, `.pg-figure-strip` (same block); `commerce/orders-list-figure-strip.md`.
- Commissions (`view_commissions.php`): the four totals as cards in `.pg-figures`.
- Edit Invoice (`edit_erp_invoice.php`): the Settlement card's Invoice Total, Paid, Returned, Outstanding.
- Receipt (`erp_receipt.php`): the status card's Amount value and its foreign-currency note; the label stays the status list's.
- Marketplace Products: the counts of its three heroes (`b.pg-figure-value` + `span.pg-figure-label`); the screen's own `.mpp-stat` size and ink rules are gone.
- No settlement screen exists beside Edit Invoice's Settlement card: settlements are read only by `edit_erp_invoice.php` and `erp_receipt.php`.
