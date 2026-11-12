---
name: decisions-made-product-46
description: Decisions list, product part 46 (2026-11-12 on): a picture saved again from the Design Canvas shows its new pixels at once wherever the panel draws it
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does a picture saved again from the Design Canvas show at once? (owner, 2026-11-12, #8882)
- Yes: wherever the panel draws it, first of all as a profile picture; no cache may keep the old pixels.
- Owner (2026-11-12), with a picture of the header's old face beside the File Manager's new preview: "ben bu işi yapalı 3 gün oldu önbellek sıfırladım tarayıcı önbelleğide boşalttım ctrl + f5 ile ama hala profil resmim orjinali. tuvalden güncellenen görselin önbelleği sıfırlanmalı."
- The architect's, not the owner's words: the face's address carries the file's change stamp, and a save drops the site's own copies. Built in #8914: `platform/picture-change-stamp.md`; the site keeps no copy of a picture (its service worker answers only page loads), so there was nothing to drop.
