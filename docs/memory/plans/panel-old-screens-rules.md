---
name: panel-old-screens-rules
description: Old panel screens to look A: what the looks decided beyond the sheet: the owner's kept screens, address parts, a narrow field alone in its row, mapping grids, values the sheet does not name
---

## What holds for the screens the owner likes as they are?
- The list is `workflow/decisions-made-panel-3.md`: Workspace, `product_builder`, `edit_user`, `welcome`, `view_product_groups`, `view_offers`, `edit_offer`, header, footer and the main menu. They are in no group.
- Architect (2026-11-15, channel message 4371 and the answer under 4385): a shared rule of a screen family reaches them as it reaches every screen of the family (the card and the save bar of #8934, the measure, the 16 px field gap).
- Their own markup and style blocks are changed by no group step. A change of their own is made only after the owner has seen its picture; the architect asks him.
- So C2 left `product_builder` as it is, C3 left `edit_offer`, and #9658 brings the field recipe without `product_builder`'s spans.
- The line is the file (architect, 2026-11-15, asked by the merging session on LM2 #9680): a rule in the shared stylesheet or the output of a shared function reaches a kept screen; an edit in a kept screen's own file does not, whatever recipe it brings. #9680 gave up its two hunks (the Done button of Edit User's Sessions drawer, New Offer on Offers, both to `pg-button pg-button-fill`).
- Owner (2026-11-15, `workflow/decisions-made-panel-9.md`): those two buttons and `product_builder`'s field widths take the new look, as KS1 and KS2 of `panel-old-screens-shared-steps.md`. Nothing else in a kept screen's own file changes without his look.

## What do the fields follow where the sheet is silent?
- Address parts: the address line takes the whole row; under it district, city, state or province, postal code and country sit in one row of quarters, `col-12 md:col-3` each; a fifth part wraps to the next row.
- Two short names that belong together share a row, 6 + 6: bank and branch, first and last name. An IBAN is `md:col-6`.
- A narrow field alone in its row is `col-12 pg-field-span-N`: the control keeps N columns, the label stays on one line and the help runs the row's width under the field, never beside it. Fields side by side keep their help under themselves.
- A lone date after a 6 + 3 row joins that row: Account 6, Date 3, Valid until 3.
- A column-mapping grid (an import's file columns, a select under each) is one control group of like selects and keeps its compact columns; the sheet's widths are for a record's fields (E5, #9704).
- Help text runs under its field from the field's left edge: a `form-text` line carries no `text-end`. Text in an input starts at the left, an e-mail address too; only a figure is end-aligned.

## What stays as it is in a group step?
- A value the sheet does not name: the size of an icon glyph outside an icon tile, and a figure outside the report block (a record's amount in a status card, the settlement figures, the hero figures of Marketplace Products). The shared steps move them: `panel-old-screens-shared-steps.md`.
- The look's section title carries no icon; only `product_builder` keeps its own.
- The VAT report (`erp_vat_report.php`) belongs to the report screens' plan (RS8a, RS8c): E3 changes nothing in it.

## How do two open PRs share a class?
- An ERP card that starts with a table carries `erp-list-card` (E2, #9699): clipped to the card's corners. The span classes come with #9658.
- A class whose rule comes with another open PR is used before that PR lands: until then it does nothing, and the PR body says so. No second rule is written, so the two PRs do not meet in the stylesheet.
- Sample senders and accounts in tests carry made-up names, never a real agent or person of the team.
