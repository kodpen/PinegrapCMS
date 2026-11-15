---
name: panel-old-screens-shared-steps
description: Old panel screens to look A: the shared steps cut after the groups, SH1 to SH10 (field, status list, ERP lists, figure cards, icon buttons, save bar, list title) and KS1, KS2 on kept screens
---

## Why are these not in a group step?
- Each changes every screen of a family at once, so it is one step with one look, cut by the architect (2026-11-15, channel message 4671 and the answer to request 512) once LM2 (#9680) had landed. A group step changes nothing for them and names them as "seen, not this step".

## Which have landed?
- SH4 figure cards (#9725); SH7 the "Action" label under a stacked list row's buttons on a phone (#9734); SH9 a list's title with its count, the segmented filters and the icon tile of settings rows (#9726); KS1 the two buttons of kept screens (#9721).
- Offers' count is asked of the owner (poll 46, message 4850): by his answer one small `[iş]` fix.

## Which are open?
- SH1 (S, #9727) The field: labels and inputs of add and edit screens take the look's field label (13 px, weight 550) and input through one shared rule in `backend.src.css`, in no screen's own file; Settings, the designer and the site side keep theirs. The owner said yes (`workflow/decisions-made-panel-9.md`).
- SH3 (M, #9744) ERP lists: the plain table's head (12 px, weight 550, third text) and rows on the card's surface, the status badges as `.pg-pill`, the filter card's labels as `.pg-field-label`.
- SH5 (S, #9739) Icon-only toolbar buttons and menu toggles as `.pg-icon-button`, their names through `lang()`. Marketplace Settings is not in it: its button ranks are SH10's.
- SH6a (M, #9746) The save bar, first part: one shared helper (`pg_save_bar()`) draws the framed bar and the bare sticky button rows take it.
- SH10 (M, #9755) The Marketplace screens: the view buttons, the filter chips and the button ranks. One filled button per view (the toolbar's); a card's own actions share one rank, the same across the view's cards and the status column.

## Which can be given now?
- SH2 (S) The status column's label and value list, one recipe: Edit Invoice, Receipt, the count list of Marketplace Settings.
- SH8 (S) The list table embedded in an edit screen (the included products of Edit Product Group).
- KS2 (small fix, `[iş]`, no look) Add Product's field widths, by the owner's answer (`workflow/decisions-made-panel-9.md`); #9658 has landed.
- SH6b, SH6c (after SH1 has landed) The bar's buttons in the sheet's order (ghost, quiet, filled), changed in the markup and not by CSS order; the phone bar where three actions take three full rows.

## What does every shared step keep?
- Claim as `[plan:panel-old-screens] <step> — <topic>`. A file an open step's PR already changes is left to the later step and named in the PR body.
- Pictures before the `ready` label: light at 1440 and 390 and one dark. A change of a few pixels gets a close-up pair, before and after side by side at three times the size (`workflow/owner-contact-channel.md`).
- A kept screen (`workflow/decisions-made-panel-3.md`) is left out of a shared step and named in its PR body: SH5, SH6a (`edit_offer`), SH7 (Offers' phone row). The architect gathers these and asks the owner once.
