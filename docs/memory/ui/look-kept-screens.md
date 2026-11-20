---
name: look-kept-screens
description: The owner's kept panel screens in look A (Edit Offer, Product Builder, Edit User, Offers…): which shared rules reach them, the file line, KS1 and KS2, what each shared step left out of them
---

## Which screens are kept?
- The owner's list of screens he likes as they are: `workflow/decisions-made-panel-3.md` (Workspace, `product_builder`, `edit_user`, `welcome`, `view_product_groups`, `view_offers`, `edit_offer`, header, footer, main menu). `tools/screenshots/screens.json` marks them `kept`.

## What reaches a kept screen?
- Architect (2026-11-15, channel message 4371 and the answer under 4385): a shared rule of a screen family reaches them as every screen of the family (the card and save bar of #8934, the measure, the 16 px field gap).
- Architect (2026-11-15, asked on LM2 #9680): the line is the file. A rule in the shared stylesheet or a shared function's output reaches a kept screen; an edit in a kept screen's own file does not, whatever recipe it brings. #9680 gave up its two hunks there.
- A change in a kept screen's own file is made only after the owner has seen its picture; the architect asks him.

## What did the owner let in?
- Owner (2026-11-15, `workflow/decisions-made-panel-9.md`): Edit User's Sessions drawer Done and Offers' New Offer take `pg-button pg-button-fill` (KS1, #9721); Add Product's field widths (KS2, #9754, `look-field-widths.md`). Nothing else in a kept screen's own file changed.
- All Offers' record count beside its title (owner, poll 46, `workflow/decisions-made-product-57.md`), #9804.

## What did the shared steps leave out of them?
- C2 left `product_builder` as it is, its own section titles with their icons too; C3 left `edit_offer` (its width went with #9658).
- SH5: their icon buttons. SH6a: `includes/templates/edit_offer.php` keeps its own bar. SH6b: `product_builder.php` and `edit_offer` keep the old button order. SH6c: `.pg-bar-rows` keeps them two per row (`save-bar-phone.md`). SH7: Offers' phone row.
- A later step leaves them out the same way and names them in its PR body; the architect gathers these and asks the owner once.
