---
name: legacy-retirement-prerequisites
description: Plan (2026-10-19, R1/R3/R4/R6 done, R2 in slices): regions editable in the visual designer before classic pages move, content rights kept
---

## Why?
- Owner (2026-10-19): the legacy page designer may retire only when every existing page moves without loss; common and designer regions must be editable in the visual designer as shared-component-like blocks, keeping the content rights of the people who edit them today (managers, role-3 users granted a region in `users_common_regions_xref`).

## What has to be built? (one PR each)
- R1 Content-editable shared component: done, schema 11.0.8 (`designer/shared-component-content-editing.md`).
- R2 Common region -> shared component (one per `cregion` with `cregion_designer_type = 'no'`), in slices:
  - R2a done (PR #2229): conversion on the region screen and the designer's region node, content in one `_editable` block, grants carried over, the `<cregion>` tag draws the component, inline editing and `save_region_content.php` refused (`designer/common-region-to-shared-component.md`).
  - R2b done (PR #2253): a component opened on its own in the designer, links from the region screen and front-end edit mode (`designer/shared-component-opened-alone.md`). Left: a site with no visual design cannot open it.
  - R2c next: the paths still writing a converted row: `image_editor_edit.php`, `find_and_replace.php` (`cregion_common`), the translation store (`includes/translate/store.php`), and the user screens' common-region grant list (hide converted regions; grants live on the component).
- R3 Designer region -> designer-only shared component: done (#1806, `designer/designer-region-to-shared-component.md`).
- R4 Dynamic region stays PHP: done (#1788, `designer/dynamic-region-in-designer.md`).
- R5 Page regions: converting a page moves its `pregion` content into the page tree as `_editable` rich-text blocks; page edit rights unchanged.
- R6 Widgets for the data-driven regions: done (menu, menu sequence, ad region, tag cloud, "Make designable": `designer/region-node-to-widget.md`).
- Missing system widget types: `system-widget-missing-types.md`. Custom layouts of classic pages (half the pages of the starting site; `edit_page.php` and the design's page queries hide them): `legacy-layouts-in-designer.md`, Y1 first.

## What did the owner answer? (2026-10-27, "önerilerin hepsi evet")
- Entry for a role-3 user granted a component but no folder edit right: they open the component on its own in the designer, not from a page they can view. Reason: their right is on the component, not on any page; a page entry would tie it to folder rights they never had.
- R2's source is the shared component; the classic `<cregion>` tag renders the component, the `cregion` row is kept (unread) until L5. Reason: one source of truth, so a classic page and a converted page never show two versions.
- Accepted: front-end inline editing of common and page regions ends for converted pages; content people edit in the designer. Reason: one editor for designed content, the one that keeps content rights (`_editable`, the merge gate).

## Where to start?
- R2c (above); R5 can run beside it. L1-L5 in `legacy-page-designer-retirement.md` wait for R1-R3.
