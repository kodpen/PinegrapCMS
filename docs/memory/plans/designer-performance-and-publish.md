---
name: designer-performance-and-publish
description: Plan (owner 2026-11-06): the visual designer stops rebuilding everything on every edit and opens faster, and Publish lists the pages to put on the site; steps D1-D7, one worker at a time on the script
---

## What is wrong today?
- `assets/js/style_designer.src.js` is one file of 78,000 lines (4.9 MB, 2.1 MB served): every opening parses it whole.
- Every edit calls `render()`, which empties the canvas body and rebuilds the whole page from the tree, then redraws the Properties, Status bar, Layers, HTML tree, Attributes and Styles panels, the badges, the validation and the live preview, and serialises every cached shared component for its snapshot (`_sdSharedBaseline()`); `render()` is called from 160 places. The dirty checks (`_pgBaselineOf()`) serialise whole page trees; the file holds 141 `JSON.stringify` calls. On a template's big pages each click and key rebuilds everything: the owner sees it on opening and while editing.
- Publish: the main button on a live tab saves the whole design but leaves drafts as drafts; a draft tab's second button publishes only that page; "Publish every page" sits in the overflow menu. Decided: `workflow/decisions-made-product-36.md`.

## Who holds it?
- One coordinator. D1-D5 edit the same file: one worker at a time, in order. D6 and D7 touch other files and run beside them. Claim as `[plan:designer-performance-and-publish] D<n> — <topic>`; under 1,000 product lines each, a red test first, `php tools/build_js.php` after the edit, memory in `designer/`.

## Which steps?
- D1 (S) Measure. Done (#7987): `designer/designer-performance.md`.
- D2 (M) Incremental canvas. Done (#8048): `designer/designer-incremental-canvas.md`. A one-node text, property or style edit redraws that element in place; structure changes, shared components, parts and props other nodes read keep the full rebuild. Browser `edit` numbers of the probe still to be taken.
- D3 (M) Panels on demand. Done (#8075): `designer/designer-panels-on-demand.md`. Hidden panels are skipped and drawn when shown; typing draws the panels after a pause; one node's Layers rows are redrawn in place.
- D4 (S) Lazy dirty checks. Done (#8091): `designer/designer-lazy-dirty-checks.md`. The dirty answer of a page not open is kept until the epoch, its tree, baseline or settings change; the open page is compared every time. Before it, no dirty check ran per render (the 1.5 s poll compared the open page only after an edit), so the shared snapshots stay as they were: taken once per component.
- D5 (M, several PRs) Parts. After D4. The file is cut into parts bundled in order by `tools/build_js.php` (as `pg_image_editor_<part>.js`, `ui/image-editor-parts.md`); the parts not needed to open a page (AI, code view, theme panel, Google fonts browser, interactions, import) load on first use through one loader; D1's probe shows the opening before and after. One PR per part. Done: part 1 loader and AI (#8112), part 2 definePart, kit, import (#8131); part 3 code view runtime (#8166); part 4 the code view's typed-changes engine (#8213); part 5 theme panel (#8245); part 6 Google Fonts browser (#8268); part 7 interactions (#8281). D5 is complete (`designer/designer-parts-on-demand.md`).
- D6 (M) Publish sheet. Done (#8009): `designer/designer-publish-sheet.md`.
- D7 (S) Save feedback. Done (#8073): `designer/designer-publish-sheet.md`.

## What stays as it is?
- The save path and its checks (`pg_designer_save_page()`), the lock on the save path, the drafts model (`page-drafts.md`), the canvas's Bootstrap 5.3.8 output, the three-place rule for widget layouts. A new question goes to the architect.

## Where does it start?
- D5 once D4 (#8091) has merged, one part per PR (start with the AI part: the largest and least needed to open a page); measure `open` with `?sd_perf=1` before the first part. D2-D4 still lack browser numbers of the probe.
