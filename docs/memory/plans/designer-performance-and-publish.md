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
- D1 (S) Measure. Done (#7987). A timing probe behind `?sd_perf=1` (open, first draw, one edit, tab switch, save; to the console and a small badge), a big fixture page under `tests/fixtures/designer/`, a Node test timing the pure parts of `render()`; the numbers in `designer/designer-performance.md`. No behaviour change.
- D2 (M) Incremental canvas. After D1 and own-looks B0. A text, property or style edit redraws only the edited node's element (`buildNodeEl()` of that subtree swapped in place; ids, selection and scroll kept); the full rebuild stays for structure changes (add, move, delete, undo, tab switch, shared component edits). The toggleable open state and the editor-state classes survive as today.
- D3 (M) Panels on demand. After D2. Only the visible panel redraws; the Layers tree updates the changed row and its ancestors; in-place typing debounces the panels and the validation while the canvas text stays live.
- D4 (S) Lazy dirty checks. After D3. The page baseline and the shared snapshots are compared on tab switch and at save, not after every render; `_pgPageUnchanged()` keeps its rules (`multi-page-designer-editor.md`).
- D5 (M, several PRs) Parts. After D4. The file is cut into parts bundled in order by `tools/build_js.php` (as `pg_image_editor_<part>.js`, `ui/image-editor-parts.md`); the parts not needed to open a page (AI, code view, theme panel, Google fonts browser, interactions, import) load on first use through one loader; D1's probe shows the opening before and after. One PR per part.
- D6 (M) Publish sheet. Done (#8009). Pressing Publish opens a sheet: every page of the design with its state (draft, on the site, scheduled, variant held by an experiment) and whether it changed; drafts and changed pages checked; "Publish selected" sets the states (`_pgSetDraft()`) and makes the one save; "Save" on a draft tab stays. Its own file `assets/js/pg_designer_publish.js`, the markup in `includes/designer_screen.php`; the overflow menu's four items move into the sheet.
- D7 (S) Save feedback. After D6. The buttons show progress during the save; after it the sheet lists which pages went on the site and which stayed drafts (today a toast).

## What stays as it is?
- The save path and its checks (`pg_designer_save_page()`), the lock on the save path, the drafts model (`page-drafts.md`), the canvas's Bootstrap 5.3.8 output, the three-place rule for widget layouts. A new question goes to the architect.

## Where does it start?
- D1 (#7987) and D6 (#8009) are done. D2 waits for B0 of `plans/design-template-own-looks.md` (#8022): B0 also edits `style_designer.src.js` and takes its turn between D1 and D2. Claim D2 once B0 has merged; D7 (after D6) is free beside it.
