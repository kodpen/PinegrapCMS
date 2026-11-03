---
name: legacy-to-designer
description: Plan (owner 2026-11-03, started): classic designs moved to the visual designer in bulk with a readiness report and a dry run, search over trees, Migration carrying designer content (LD1-LD5)
---

## What is this plan?
- The owner's order on #6006 (2026-11-03): the migration of designer content between sites joins the move of legacy designs into the visual designer, as the next plan.
- Today every conversion exists one at a time, run by a designer: a classic style opened as a design (`designer/classic-style-to-design.md`), its folder pages at Save, common and designer regions as shared components, page regions into the page tree, menu/ad/tag-cloud nodes as widgets (`designer/classic-regions-in-designer.md`). A site with hundreds of pages has no overview, no bulk run and no report; the Migration screen copies classic columns only (`decisions-made-product-21.md`, #6006).
- The legacy rule holds (`feature-roadmap-2026-10.md`): nothing classic is removed, the renderers stay, conversion never runs by itself. This plan adds a guided bulk run on top of the existing converters.

## Which steps, in one line each?
1. LD1 (M) Readiness report, done (#6219, `designer/legacy-to-designer-report.md`): a "Move to the visual designer" screen off `view_styles.php` listing every classic style with its pages (own and by folder), its regions by type, custom layouts, PHP regions, the refusal reasons (`pg_classic_style_refusal()`), locks, and a ready / needs-attention state per style; pure readers, tests; writes nothing.
2. LD2 (M) Bulk run with a dry run: the chosen styles converted in the existing order (common and designer regions to components, the style to a design with its folder pages, page regions into trees, region nodes to widgets) by the existing converters, as a background job of the general job runner with progress; a dry run lists what each would write; a page somebody holds is skipped and listed; a kept report; undo stays the existing paths (component delete, page revisions).
3. LD3 (M) Search over trees, done (#6257, `designer/tree-search.md`): site search, find and replace and the panel's content search read designer page trees and shared component trees (the open items of `classic-regions-in-designer.md`), so a converted site loses no search.
4. LD4 (M) Migration carries designer content (#6006 option A), done in #6221 (`designer/migration-carries-designs.md`): the source's `api.php` migration actions expose the designer columns of pages and styles, the design's managed files and theme, shared components; the target remaps the ids inside the trees (styles, pages, regions, files, components) the way the template-from-design export does (`designer/design-templates-from-design.md`, `pg_dtc_*` placeholders: reuse it, no second serializer); bin, draft and Workspace folders stay skipped; a report.
5. LD5 (S) Close-out: the report's "stays classic" list (dynamic PHP regions, custom layouts, frozen gateways), memory, the open `cregion` rows question left as decided (#3156).

## Which decisions bind it?
- Owner 2026-10-19 and 2026-10-27 (`designer/page-designer-retired.md`, `classic-regions-in-designer.md`): the designer runs a conversion, one source after it, front-end inline editing of converted content ends, the classic rows stay unread. Normalised output is fine; byte-identical is not a goal.
- LD2 writes what the single Save writes, nothing else; the dry run comes first on every run. No schema change is expected; one would be asked (Working mode 10).
- Sizes about 1,000 product lines; texts through `tr_add.php`; each step writes its memory file and tests; claim as `[plan:legacy-to-designer] LD<n>`.

## Open questions?
- None; the owner said start on 2026-11-03. LD1 and LD4 are `free`, claimed as `[plan:legacy-to-designer] LD<n>` (`feature-plans-2026-11.md` rules); LD2, LD3 and LD5 open as their "after" steps land.

## Where to start?
- LD1 is done (#6219); LD4 runs on its own; LD2 starts from `pg_ltd_report()`; LD3 is done (#6257); LD5 last.
