---
name: save-bar-phone
description: The save bar below 576 px (SH6c): three actions share one row with their icons hidden, others two per row, .pg-bar-rows keeps kept screens on two per row
---

## How does a phone draw three actions?
- On one row, not three: below 576 px the shared bar gives each action half a row, so a long third label (Save & Return) took a row of its own. A bar with exactly three `.btn` (counted with `:nth-child(1 of .btn):nth-last-child(3 of .btn)`, so the hidden Return button is not counted) gives each its label's width and shares the rest; icons are hidden there, a label too long ends in an ellipsis. One, two, four or more actions keep two per row; `.pg-bar-icons` keeps icons only.

## Which bars stay two per row?
- `.pg-bar-rows` on the `nav` keeps two per row: Product Builder and Edit Offer (kept screens) carry it. Pinned by `tests/save_bar_phone_test.php`.

Related: docs/memory/ui/save-bar-helper.md, docs/memory/ui/look-edit-screens.md
