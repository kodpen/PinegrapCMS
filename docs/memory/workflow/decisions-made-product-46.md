---
name: decisions-made-product-46
description: Decisions list, product part 46 (2026-11-12 on): a picture saved again from the Design Canvas shows at once; the Legacy helper after its census (#8942); a plan for the custom layouts
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does a picture saved again from the Design Canvas show at once? (owner, 2026-11-12, #8882)
- Yes: wherever the panel draws it, first of all as a profile picture; no cache may keep the old pixels.
- Owner (2026-11-12), with a picture of the header's old face beside the File Manager's new preview: "ben bu işi yapalı 3 gün oldu önbellek sıfırladım tarayıcı önbelleğide boşalttım ctrl + f5 ile ama hala profil resmim orjinali. tuvalden güncellenen görselin önbelleği sıfırlanmalı."
- The architect's, not the owner's words: the face's address carries the file's change stamp, and a save drops the site's own copies; done in #8913 (`platform/panel-picture-change-stamp.md`); the service worker keeps no picture, so no save drops anything.

## How does the Legacy to Bootstrap helper go on after its census? (owner, 2026-11-12, #8942)
- The plan goes on as written; LB6's twins are the photo background, the slider and the lightbox, no navbar; the theme becomes a palette and a look after the pages are moved; the helper is also tried on a design that is not Bootstrap.
- Owner (2026-11-12), shown the trial's two pictures and asked 1 (near enough to go on: a yes), 2 (twins: a those three, no navbar), 3 (palette and look: a yes, after the move): "1a, 2a, 3a" and "bazı sitelerde materialize bile olan var bir de bootstrap dışı bir tasarımla da test etmek gerekecek."
- The architect's, not the owner's words: the Materialize trial is step LB1b, and the map rules, the trial's own `scripts.js` and the compatibility sheet are in `plans/legacy-bootstrap-helper-rules.md`.

## Is a plan written for the custom layouts' move to Bootstrap 5.3.8? (owner, 2026-11-12, #8942)
- Yes: `plans/legacy-layouts-bootstrap.md`.
- Owner (2026-11-12), offered three ways to fill a week (1 the remaining pages of the panel's reference sheet, 2 the storefront API before the release, 3 a new plan for the custom layouts): "1 ve 3, rapor ekranlarını çiz".
- The architect's, not the owner's words: way 2 was not chosen, so the storefront API still starts after the release.
