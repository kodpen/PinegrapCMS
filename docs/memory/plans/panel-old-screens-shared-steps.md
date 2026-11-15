---
name: panel-old-screens-shared-steps
description: Old panel screens to look A: the shared steps cut after the groups, SH1 to SH10 (field, status list, ERP lists, figure cards, icon buttons, save bar, list title) and KS1, KS2 on kept screens
---

## Why are these not in a group step?
- Each changes every screen of a family at once, so it is one step with one look, cut by the architect (2026-11-15, channel message 4671 and the answer to request 512) once LM2 (#9680) had landed. A group step changes nothing for them and names them as "seen, not this step".

## Which steps go first?
- SH1 (S) The field: labels and inputs of add and edit screens take the look's field label (13 px, weight 550) and input through one shared rule in `backend.src.css`, in no screen's own file; Settings (`.pg-settings-body`, `.pg-set-card`), the designer and the site side keep theirs. It changes the owner's kept screens too, so its pictures go to the owner: Add Product, Edit User and two more, with a close-up pair of one field.
- SH4 (M) Figure cards: one recipe for a figure outside the report block, the report figure's label, value and note as shared classes: Orders, Commissions, the settlement figures, a record's amount in a status card (Edit Invoice, Receipt), the hero of Marketplace Products. Only the figure blocks.
- SH7 (S) The stacked list row on a phone: the "Action" label under the row's buttons.
- SH8 (S) The list table embedded in an edit screen (the included products of Edit Product Group).
- SH9 (M) A list's title with its count and segmented filters, and the icon tile of settings rows; Marketplace Products' view buttons and filter chips are SH10's. Offers' count is asked of the owner (poll 46, message 4850): SH9 goes ready with Offers as it is, the count there follows by his answer.
- KS1 (small fix, `[iş]`, no look) The two hunks LM2 gave up: "Done" in Edit User's Sessions drawer and "New Offer" on Offers to `pg-button pg-button-fill` (#9721). KS2 (the same kind, once #9658 has landed): Add Product's field widths. Both by the owner's answer (`workflow/decisions-made-panel-9.md`).

## Which were given once E3 and E5 had landed?
- On 2026-11-15 (channel message 4877), each leaving a file an open PR changes:
- SH3 (M) ERP lists: the table's head (12 px, weight 550, third text) and rows, the status badges as `.pg-pill`, the filter card's labels as `.pg-field-label`. The list's title and count, `includes/fn/list_table.php` and `look_list.php` are SH9's.
- SH5 (S) Icon-only toolbar buttons and menu toggles as `.pg-icon-button`, their accessible names through `lang()`.
- SH6a (M) The save bar, first part: one shared helper draws the framed bar, and the bare sticky button rows (`nav.buttons.navigation` with `position-sticky`, floating over the cards with no bar behind them) take it.
- SH6b, SH6c (after S2 #9692 and SH1 have landed) The bar's buttons in the sheet's order (ghost, quiet, filled), changed in the markup and not by CSS order; the phone bar where three actions take three full rows.

## Which wait for SH4 to land?
- SH2 (S) The status column's label and value list, one recipe: Edit Invoice, Receipt, the count list of Marketplace Settings.
- SH10 (M) The Marketplace screens: the view buttons, the filter chips, one filled button per view (they have three).

## What does every shared step keep?
- Claim as `[plan:panel-old-screens] <step> — <topic>`. A file an open step's PR already changes is left to the later step and named in the PR body.
- Pictures before the `ready` label: light at 1440 and 390 and one dark. A change of a few pixels gets a close-up pair, before and after side by side at three times the size (`workflow/owner-contact-channel.md`).
- A kept screen (`workflow/decisions-made-panel-3.md`) is left out of a shared step and named in its PR body: SH5, SH6a (`edit_offer`), SH7 (Offers' phone row). The architect gathers these and asks the owner once.
