---
name: decisions-made-product-112
description: Decisions made, product part 112 (2026-11-26 on): a My library item added on a site that lacks its pictures says how many are missing; the addresses stay, the export carries no picture files
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
