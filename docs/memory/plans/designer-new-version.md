---
name: designer-new-version
description: Plan (owner 2026-11-26): a live page's new version prepared in a draft that replaces it, from "Save as a new version" in the Page Design Studio; steps NV1 to NV5
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel message 14636): a designer makes a new version of a page that is on the site, perhaps on top of the live page, and needs a "Save" that does not publish and the phone QR code for that draft. Poll 244: "Yeni sürüm taslağı" (`workflow/decisions-made-product-116.md`).
- A live page's own Publish still goes live at once (his decision of 2026-10-06 stays): no working copy inside a page.

## What is there today? (read in memory and the code, 2026-11-26)
- A draft may stand for a live page: a copy in Drafts held by a release bundle or an experiment (`designer/release-bundle.md`). Publishing the bundle writes its tree, title and description onto the live page; the used copy goes to the Recycle Bin.
- The only doors are the Bundles screen, which no menu opens, and Page Settings › Experiments. A bundled draft is a held row of the Publish sheet: the studio cannot publish it.
- A draft tab has Save, the phone preview, share links, approval and Compare.

## What is a new version?
- A bundle that is open, has no publish time and holds one item, a replacement. No table, no column, no mark of where it came from: a bundle made by hand with that shape acts the same. A second page added or a time set makes it a plain bundle, held as today.
- One per live page (the bundle's own rule); none while the page has an open experiment.

## Which steps?
- `designer-new-version-steps.md`: NV1 the server, NV2 the door and the tab, NV3 Publish, NV4 the phone and Compare, NV5 the Bundles menu row and the close-out. Each waits for the one before it.

## What does every step keep?
- Bundle writes only through `includes/fn/page_bundles.php` under its lock, a page only through `pg_designer_save_page()`; every hold and refusal of `designer/release-bundle-tables.md` and `designer/prototype-approval-hold.md`.
- A step that finds it needs a column or a table stops and asks the architect.
- `designer/designer-light-and-simple.md`: no button that stays in a bar, rows of menus that are there, a designer part loaded on first use. Texts through `_sdT()` and `lang()`; the sheet's parts copied from the Publish sheet as built.
- Starting a version never puts another page's pending change on the site, and the live tab is clean afterwards: the next Save of the draft writes the whole design (`designer/page-drafts.md`).
- For full access with drafts ready, as the Publish sheet; a content-level editor's change follows `designer/content-proposals-overview.md`.

Related: docs/memory/plans/designer-new-version-steps.md, docs/memory/designer/release-bundle.md, docs/memory/designer/designer-publish-sheet.md
