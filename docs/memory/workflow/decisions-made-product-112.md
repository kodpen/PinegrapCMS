---
name: decisions-made-product-112
description: Decisions made, product part 112 (2026-11-26 on): a My library item says how many pictures the site lacks; a payment way in test on a live shop gets a System Status warning row
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-111.md`.

## What does a My library item do about pictures the site lacks? (owner, 2026-11-26, agent channel poll 224, message 13858)
- Found by the My library bug hunt (#11750): an item exported and imported on another site shows broken pictures where that site has no file of the same address; the export file carries no picture files and adding the copy said nothing.
- Asked with three choices: the line shown when the copy is added says how many pictures are not on this site and the addresses stay; the export file carries the picture files and the import uploads them; or as before, nothing said.
- Owner: "Ekleme satırı kaç resmin bu sitede olmadığını söylesin, adresler kalsın".
- So: adding a copy counts its pictures this site lacks and says the number in its line; a picture's address is never cleared or changed; the export file keeps its format and carries no picture file.
- What bound the question (architect): a picture is a file, not a record, so its address is the only trace of which one was meant and the file put at that place repairs it; a missing page, folder or menu is a record and is cleared and marked as before.
- The rule in full and what the step builds: `designer/designer-library-missing-pictures.md`.

## Does a payment way in test mode on a live shop show on System Status? (owner, 2026-11-26, agent channel poll 225, message 14071)
- Found by the site set-up journey hunt (#11807): a payment way put back to test on a shop that has gone live (a Stripe test key, PayPal sandbox, PayTR test) showed only on the "Set up your site" card; once the card is closed nothing on the panel says it, and customers cannot pay for real.
- Asked with three choices: a warning row on System Status; the row and a daily operations alert as well; or nothing.
- Owner: "Evet, System Status'ta uyarı satırı".
- So: System Status gets one warning row for it, only on a site whose shop is on and that has gone live; no operations alert, no schema change.
- Architect (2026-11-26, agent channel request 3108): "gone live" is the set-up journey's own go-live time; a site that never pressed "Go live" gets no row and reads it on its card. A way is in test by the card's own rule (`pg_launch_journey_payment_live()`), so the card and the row never disagree; a way whose mode cannot be read is not a warning. The row names each way in test and links to where its mode is set.
