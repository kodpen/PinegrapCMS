---
name: panel-old-screens-shared-steps
description: Old panel screens to look A: the shared steps cut after the groups: field label and input, status list, ERP list table and badges, figure cards, buttons after LM2, list title
---

## Why are these not in a group step?
- Each changes every screen of a family at once, so it is one step with one look, cut by the architect once LM2 (#9680) and the groups it touches have landed. A group step changes nothing for them and names them as "seen, not this step".

## Which steps are they?
- The field: labels and inputs of add and edit screens take the look's field label (13 px, weight 550) and input; they are still at Bootstrap's size. It changes the owner's kept screens too, so its pictures, Add Product and Edit User among them, go to the owner.
- The status column's label and value list, one recipe: Edit Invoice, Receipt, the count list of Marketplace Settings.
- ERP lists: the table's head (12 px, weight 550, third text) and rows, the status badges as `.pg-pill`, the filter card's labels as `.pg-field-label`.
- Figure cards: one recipe for a figure outside the report block: orders, commissions, the settlement figures, a record's amount in a status card, the hero of Marketplace Products.
- Buttons after LM2 (SH5, done #9739): icon-only toolbar buttons and menu toggles as `.pg-icon-button`, their accessible names through `lang()`; one filled button per view (the Marketplace screens have three).
- The save bar: its buttons in the sheet's order (ghost, quiet, filled), changed in the markup and not by CSS order, with the phone bar where three actions take three full rows.
- The stacked list row on a phone: the "Action" label under the row's buttons.
- A list table embedded in an edit screen (the included products of Edit Product Group).
- A list's title with its count and segmented filters, with the view buttons and filter chips of Marketplace Products; the icon tile of settings rows.
