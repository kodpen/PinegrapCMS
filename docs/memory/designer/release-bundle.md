---
name: release-bundle
description: Start file of release bundles (Bundles screen, page_bundles.php): decisions, parts, which file holds what, B1-B18 as built, the gaps
module: page_bundles
---

## What is a release bundle?
- A named list of visual-design drafts on the Bundles screen: new pages, and replacements (a copy of a live page in Drafts standing for it, as an experiment's variant). Previewed together, published at one moment (by a time or over the API; the screen's Publish is missing, #10557), taken back together. Nothing is staged inside a page.
- Built by plan release-bundle (2026-11-16 to 2026-11-20), steps RB1 #9961, RB2 #9998, RB2b #10191, RB3 #9999, RB4 #10108, RB5 #10296, RB6 #10518, RB7 #10482; fixes #10045 to #10048, #10158.

## Which decisions bind it?
- Owner (2026-11-16, agent channel poll 63, message 5748): a draft may stand for a live page outside an experiment ("Evet, geçebilsin"). The decision of 2026-10-06 stays whole: no working copy, a live page's own Publish goes live at once (B17).
- Architect (2026-11-16, agent channel message 5763): a failure while writing takes back what was written and leaves the bundle open; no copy of a page whose experiment is open; the used replacement goes to the Recycle Bin.
- Owner (2026-11-16, polls 106 and 107): the publish and take-back dialogs and the Bundles screen accepted (`workflow/decisions-made-product-67.md`, `-68.md`). Owner (2026-11-20, poll 159): the share link's column (`release-bundle-share-link.md`).
- Left out, each a later yes: shared components, menus and collection records in a bundle (B18).

## Which file holds what?
- Tables, statuses, the hold, Add a copy / Add a draft, "Changed since": `release-bundle-tables.md`.
- The Bundles screen: `release-bundle-screen.md`; Publish and Take back dialogs: `release-bundle-dialogs.md`.
- Checks, writes, a failure taken back, Take back: `release-bundle-publish.md`; a dead or failed-query publish: `release-bundle-publish-errors.md`.
- Publish time and the bell line: `release-bundle-time.md`. Preview the bundle: `release-bundle-preview.md`; Share the preview: `release-bundle-share-link.md`.
- The Publish sheet's held row: `designer-publish-sheet-held-row.md`. The API: `api/api-design-bundles.md`.

## Which features stand, checked against the code (2026-11-20)?
- B1-B4, B6: module and screen (RB1, RB2). B5: the hold in the save, bin and API delete (RB1), the sheet row (RB2b). B7-B9, B11, B12: in the module (RB3), reached by the time and the API only, see below. B13 (RB4), B14 (RB5), B15 (RB6), B16 (RB7), B17 and B18 (RB3) as written.
- B10: the Site Log names the bundle; History does not show its name yet: `pg_page_bundle_revision_names()` exists with no caller.

## What is not built yet?
- The Bundles screen has no Publish and no Take back: RB3 built the dialogs and runners but never wired them into `page_bundles.php` (bug #10557). "Publish the rest" of a partly published bundle has no button either.
- No menu row and no link from Pages to the Bundles screen; the architect's follow-up waits only on the screen's Publish now (`release-bundle-screen.md`). It is reached from the bell line and the preview's Close.
- B10's History label (above).

## How is a new part added?
- A function goes into `includes/fn/page_bundles.php` (screen helpers `page_bundles_screen.php`); a write runs under the bundle's lock (`pg_page_bundle_with_lock()`), a page through `pg_designer_save_page()`. A new status goes through `pg_page_bundle_may_move()`.
