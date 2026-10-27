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
- L2 entry point: "Open in the visual designer" on the style list for a classic style; opt-in per style, the original row is kept until the design is saved (`style_layout` becomes the designer's). Pages keep their rows, `page_type`, `layout_type`, `layout_modified`; the style keeps its `layout_type` override. Needs Y1 first, or custom-layout pages vanish from the design.
- L3 regions: done (`designer/classic-regions-in-designer.md`); here only wiring them into the conversion of a style.
- L4 parity list: written (`legacy-page-designer-parity.md`, P1-P10, one build-or-drop proposal each); waits for the owner's choices. Page-type fields, layouts: Y2-Y3.
- L5 retire: remove the three files, the `page_designer_button` branch in `includes/fn/output.php` (Ctrl+G), the help-table row, the session key `software.page_designer`; `clean_up.php` lists the files; changelog `[NEW]` line; the rest in `legacy-page-designer-l5-list.md`. Gated by Y6.

## Which rules apply?
- The legacy custom style layer is production (`workflow/decisions-made-product.md`): conversion never runs by itself; the renderers and layout templates are not touched.
- A converted page re-exported by the designer is normalised (indentation, attribute order, quoting); byte-identical output is not a goal unless the owner says so.

## What waits on this plan? (PHP region eval; owner 2026-10-19: no change now)
- `get_page_content.php` evals the assembled page when `PHP_REGIONS` is on; text and raw tokens are expanded before it and `pg_defuse_php_tags()` escapes them (`core/php-regions-eval-and-content.md`). Left: evaluating designer code only, before text and widgets are expanded.

## Open questions
- A classic style is a master layout shared by many pages; the designer stores one tree per page. Frame copied into every page (nowhere to edit it once), or one shared component holding the `page` region node (renders today; canvas shows a placeholder)? Decides L2 (the L1 converter gives one tree either way).
- Theme designer (`theme_designer.php`) and `view_styles.php`: retire with the page designer or keep for classic sites?
- A classic style on a PHP template file (`style_file`) is not convertible; it stays classic.

## Where to start?
- Y1 (`legacy-layouts-in-designer.md`) now; L2 after Y1. Left from L1 for L2: the theme's own Bootstrap and jQuery links (`{path}bootstrap.css`) are dropped by the framework filter unless `keep_framework`; `{path}{software_directory}/livesite.src.css` comes in as an asset; no panel field edits the root `title`.
