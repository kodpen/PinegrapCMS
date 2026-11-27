---
name: designer-new-version
description: Start file of a live page's new version (owner 2026-11-26): what it is, its decisions, which file holds the server, the tab, Publish, the phone and Compare, where the Bundles screen is in the menu
module: page_versions
---

## What is a new version?
- A draft that will replace a live page, prepared without touching the site: "Save as a new version" in the Page Design Studio puts a live tab's changes into it, its own Save keeps it off the site, Publish from the Publish sheet writes it onto the live page.
- On the server it is a release bundle that is open, has no publish time and holds one item, a replacement. No table or column of its own: a bundle made by hand with that shape acts the same; a second page or a time makes it a plain, held bundle.
- One per live page; none while the page has an open experiment.

## Which decisions bind it?
- Owner (2026-11-26, agent channel message 14636, poll 244 "Yeni sürüm taslağı", `workflow/decisions-made-product-116.md`): a designer needs a Save that does not publish, and the phone QR code, for a new version of a page on the site.
- The decision of 2026-10-06 stays: a live page's own Publish goes live at once; no working copy inside a page (`release-bundle.md`).

## Which file holds what?
- Server, shape rule, Start and its refusals, Discard: `designer-new-version-server.md` (`includes/fn/page_versions.php`).
- The door, the draft's tab, its "New version" pill, "Discard this version": `designer-new-version-tab.md`.
- Its row of the Publish sheet, "Replace anyway" or "Cancel": `designer-new-version-publish.md`.
- "Preview on a phone" on a live tab with changes, "Compare with the live page": `designer-new-version-phone-compare.md`.
- Bundles, holds and the Bundles screen: `release-bundle.md`, `release-bundle-screen.md`.

## Where is the Bundles screen in the menu?
- Page Design Studio row (21) › Bundles (`page_bundles.php`, `bi-collection`), after Experiments, for role < 2 as the screen's `validate_area_access($user, 'designer')`. The screen marks row 21 in `output_menu()`.

## How was it built?
- NV1 #11981, NV2 #12023, NV3 #12092, NV4 #12117, the menu row and this close-out NV5.
- Each part keeps the bundle's writes in `includes/fn/page_bundles.php` under its lock, a page through `pg_designer_save_page()`, and every hold of `release-bundle-tables.md` and `prototype-approval-hold.md`.

Related: docs/memory/designer/release-bundle.md, docs/memory/designer/designer-publish-sheet.md
