---
name: classic-regions-in-designer
description: Where each classic region type goes in the visual designer (common, designer, dynamic, page, data-driven regions), the owner's decisions behind it, and what is still open
---

## Why do classic regions move into the designer?
- Owner 2026-10-19: the legacy page designer retires only when every page moves without loss; common and designer regions must be editable in the visual designer as shared-component-like blocks, keeping the content rights of the people who edit them today (managers, role-3 users granted a region in `users_common_regions_xref`, users with a folder's edit right). It retired in 2026.11.0: `page-designer-retired.md`.

## Where does each region type go?
| Region | Becomes | Memory |
|---|---|---|
| common (`cregion_designer_type = 'no'`) | a shared component opened to content editors, grants carried over; its tag draws the component | `common-region-to-shared-component.md`, `shared-component-content-editing.md` |
| designer (`'yes'`) | a designer-only shared component | `designer-region-to-shared-component.md` |
| dynamic | stays PHP, edited from its node | `dynamic-region-in-designer.md` |
| page (`pregion`) | `_editable` blocks in the page's own tree | `page-region-into-page-tree.md` |
| menu, menu sequence, ad, tag cloud | widgets ("Make designable") | `region-node-to-widget.md` |
- The bulk move of whole styles, its report and what stays classic: `legacy-to-designer-report.md`.
- Site search and Find & Replace read the trees the converted content lives in: `tree-search.md`.
- Every conversion is opt-in, run by a designer (role 0-1); nothing converts on its own.

## What did the owner answer on 2026-10-27?
- A role-3 user granted a component but no folder edit right opens the component on its own in the designer (`shared-component-opened-alone.md`). Reason: their right is on the component, not on any page.
- A converted common region's source is the shared component; the `cregion` row and its grants are kept unread for good: no data step clears them, a per-region cleanup only if the owner asks (#3156, `workflow/decisions-made-product-3.md`). Reason: one source of truth, so a classic and a converted page never show two versions.
- Front-end inline editing of common and page regions ends for converted content. Reason: one editor for designed content, the one that keeps content rights (`_editable`, the merge gate).

## What is still open?
- A site with no visual design cannot open a component on its own.
