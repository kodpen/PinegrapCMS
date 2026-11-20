---
name: look-sheet-silent
description: Look A where the sheet is silent: address parts, paired names, a lone date, help and input alignment, values left as they are, titles without icons, a class from another open PR
---

## How are fields laid out where the sheet names no width?
- Address: the address line takes the whole row; district, city, state or province, postal code and country sit under it in one row of quarters, `col-12 md:col-3` each; a fifth part wraps to the next row (C5, #9710).
- Two short names that belong together share a row, 6 + 6: bank and branch, first and last name. An IBAN is `md:col-6`.
- A lone date after a 6 + 3 row joins that row: Account 6, Date 3, Valid until 3.
- A narrow field alone in its row, and fields side by side whose labels do not fit: `look-field-widths.md`. Column-mapping grids: `look-mapping-grids.md`.
- Help text runs under its field from the field's left edge: a `form-text` line carries no `text-end`. Fields side by side keep their help under themselves. Text in an input starts at the left, an e-mail address too; only a figure is end-aligned.

## Which values does a move leave as they are?
- A value the sheet does not name: an icon glyph's size outside an icon tile, and a figure outside the report block (a record's amount in a status card, Database Engine's side figures, Import's Summary values, the update hero). SH4 moved the figure cards it named (`look-figure-cards.md`); the rest stay.
- Inline values no token names: a sticky column's `top: 3.3rem`, the save bar column's `bottom: 0`, progress widths a script sets, popover arrows, a list's own `max-height` (Clean Up's 500 px).
- The look's section title carries no icon; only `product_builder` keeps its own (`look-kept-screens.md`).
- The VAT report (`erp_vat_report.php`) is the report screens' (`report-screens-look-review.md`); E3 changed nothing in it.

## How do two open PRs share a class?
- An ERP card that starts with a table carries `erp-list-card` (E2, #9699): clipped to the card's corners.
- A class whose rule comes with another open PR is used before that PR lands: until then it does nothing, and the PR body says so. No second rule is written, so the two PRs do not meet in the stylesheet.
- Sample senders and accounts in tests carry made-up names, never a real agent or person of the team.
