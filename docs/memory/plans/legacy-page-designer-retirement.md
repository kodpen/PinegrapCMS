---
name: legacy-page-designer-retirement
description: Plan (2026-10-19, waits for its prerequisites) to open classic pages in the visual designer and retire page_designer.php: placeholder converter L1, entry point, parity list, what leaves
---

## What did the owner decide?
- 2026-10-19: legacy pages (pages on a classic `style` row whose `style_code` is edited as text in `page_designer.php` / `edit_custom_style.php`, regions in `cregion` / `dregion`) will open in the visual designer and be edited there; the legacy page designer (`page_designer.php`, its `.js` and `.css`) then goes away for good.
- 2026-10-19 (later): only once every existing page moves without loss, in look and in features. First the prerequisites: regions as shared-component-like blocks (done: `designer/classic-regions-in-designer.md`), the missing system widget types (`system-widget-missing-types.md`) and the custom layouts (`legacy-layouts-in-designer.md`).
- The code editor (`designer-code-editor.md`) is the bridge: a converted page keeps a code view, so nothing the old text editor allowed is lost; the emitter and the converter are the two halves of the round trip.

## Steps (one PR each)
- L1 converter: done (#2337, `designer/classic-style-import.md`): classic placeholders to region nodes, `<meta_tags>`/`<stylesheet>` dropped, title and body on the root.
- L2 entry point: done (#2370, `designer/classic-style-to-design.md`): style list button, frame as one shared component, page trees placing it, Save makes the row a visual design (framework custom). Left: pages taking the style from their folder (not design pages; keep `style_code`), older cell-based system styles (not offered).
- L3 regions: done (`designer/classic-regions-in-designer.md`); wired into the conversion in #2429 (`designer/classic-style-to-design.md`): a converted common region is placed as its component; nothing converts on its own. Left: page regions inside the frame component cannot be moved into the page tree (R5 refuses them inside a component).
- L4 parity list: decided (#2484), all dropped but P7, built in #2532 (`legacy-page-designer-parity.md`, `designer/classic-style-design-files.md`). Left: the folder-pages Save option (#2411). Page-type fields, layouts: Y2-Y3.
- L5 retire: remove the three files, the `page_designer_button` branch in `includes/fn/output.php` (Ctrl+G), the help-table row, the session key `software.page_designer`; `clean_up.php` lists the files; changelog `[NEW]` line; the rest in `legacy-page-designer-l5-list.md`. Gated by Y6.

## Which rules apply?
- The legacy custom style layer is production (`workflow/decisions-made-product.md`): conversion never runs by itself; the renderers and layout templates are not touched.
- A converted page re-exported by the designer is normalised (indentation, attribute order, quoting); byte-identical output is not a goal unless the owner says so.

## What waits on this plan? (PHP region eval; owner 2026-10-19: no change now)
- `get_page_content.php` evals the assembled page when `PHP_REGIONS` is on; text and raw tokens are expanded before it and `pg_defuse_php_tags()` escapes them (`core/php-regions-eval-and-content.md`). Left: evaluating designer code only, before text and widgets are expanded.

## Open questions
- Answered 2026-10-27: the frame is (b) one shared component. The owner delegated the answer to the coordinator ("bekleyen işçilere yanıtı sen ver benim yerime", "en makul ve işlevsel seçimle"), consistent with the R2 answer that the source is the shared component.
- Answered (#2411): on Save of a converted style, "Also convert the N pages that take this style from their folder", default on, the list shown; a step after L2.
- Answered (#2437): `theme_designer.php` and `view_styles.php` stay while any classic style exists and retire in a later step once conversion covers every style.
- A classic style on a PHP template file (`style_file`) is not convertible; it stays classic.

## Where to start?
- L4's folder-pages option (#2411): Save of a converted style. Left from L1: no panel field edits the root `title`.
