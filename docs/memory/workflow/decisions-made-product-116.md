---
name: decisions-made-product-116
description: Decisions made, product part 116 (2026-11-26 on): a live page's new version is prepared in a draft that replaces it ("Save as a new version"); a live page still has no working copy
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-115.md`.

## How is a new version of a live page prepared? (owner, 2026-11-26, agent channel poll 244, message 14639)
- Owner (2026-11-26, agent channel message 14636), after choosing the live page's QR code for a design with no draft: "ya bir tasarımcı yayındaki bir sayfanın yeni sürümünü tasarlıyorsa? bugün sistemimiz buna hazırmı? o durumda mobil qr yine o taslak için lazım. sayfanın yeni sürümünü yayınlanmış sayfanın üzerine de tasarlıyor olabilir, yayınlanmış sayfayı yayınlamadan "kaydet" seçeneği de olmalı."
- What was found: a live page has one button, Publish, and the change goes live at once (his decision of 2026-10-06, `decisions-made-product.md`); what is not published lives in the browser only, so no phone can show it. A draft standing for a live page exists (poll 63, `decisions-made-product-59.md`) but only the Bundles screen and the Experiments sheet make one.
- Asked with three choices, he chose "Yeni sürüm taslağı": "Save as a new version" on a live page's tab writes the changes to a draft that will replace the page; the live page stays as it is; the draft has Save, the phone QR code, share links and approval as any draft; its Publish replaces the live page, address and title kept.
- Not chosen: a working copy inside the page with Save and Publish apart (the Webflow model he left out on 2026-10-06; he was told it needs a table or column and touches every reader of a page), and leaving things as they are with only a menu row for the Bundles screen.
- So the decision of 2026-10-06 stands in its other half: a live page's own Publish goes live at once and a live page keeps no working copy.

## What is the architect's reading, not his words?
- Built on the release bundle as it is, with no table and no column: a new version is an open bundle of one replacement (`designer/designer-new-version.md`).
- The phone preview's rule of poll 243 (`decisions-made-product-115.md`) is untouched: a design with a draft shows the draft, so a new version is what the phone shows once it is started.
- The Bundles screen gets its menu row in the same plan, as the versions are listed there and taken back from there.
