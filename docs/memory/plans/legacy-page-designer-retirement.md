---
name: legacy-page-designer-retirement
description: Plan (2026-10-19, waits for its prerequisites) to open classic pages in the visual designer and retire page_designer.php: placeholder converter L1, entry point, parity list, what leaves
---

## What did the owner decide?
- 2026-10-19: legacy pages (pages on a classic `style` row whose `style_code` is edited as text in `page_designer.php` / `edit_custom_style.php`, regions in `cregion` / `dregion`) will open in the visual designer and be edited there; the legacy page designer (`page_designer.php`, `assets/js/page_designer.js`, `assets/css/page_designer.css`) then goes away for good.
- 2026-10-19 (later): only once every existing page moves without loss. First the prerequisites: regions editable in the designer as shared-component-like blocks (`legacy-retirement-prerequisites.md`) and the missing system widget types (`system-widget-missing-types.md`).
- The code editor (`designer-code-editor.md`) is the bridge: a converted page keeps a code view, so nothing the old text editor allowed is lost; the emitter and the converter are the two halves of the round trip.

## Steps (one PR each)
- L1 converter: `pg_designer_import_html()` learns the classic placeholders `<pregion>`, `<system>name</system>`, `<cregion>`, `<dregion>`, `<menu>`, `<menu_sequence>`, `<login>`, `<ad>`, `<cart>`, `<tcloud>`, `<mobile_switch>`, `<pdf>`, `<!--pg-comments-block-->` and turns them into `region` nodes (the inverse of `regionTag()` in `generateHTML()` and `generate_style_code_from_tree()`); `<meta_tags>` and `<stylesheet>` are dropped (the frame prints them); the `<title>` and the `<body>` attributes land on the root node. Test: `tests/designer_import_legacy_test.php` with the starting site's classic styles.
- L2 entry point: "Open in the visual designer" on the style list for a classic style. Conversion is opt-in per style and keeps the original row until the design is saved from the editor; pages using the style keep their rows (`style_layout` becomes the designer's).
- L3 regions: done by the prerequisites R1-R5 (`legacy-retirement-prerequisites.md`); here only wiring them into the conversion of a style.
- L4 parity list: what `page_designer.php` does that the editor does not (browse the live site and jump to the page's style, Ctrl+G from the toolbar, console pane, region editors) - build the needed ones, drop the rest, decided with the owner.
- L5 retire: remove the three files, the `page_designer_button` branch in `includes/fn/output.php` (Ctrl+G), the help-table row, the session key `software.page_designer`; `clean_up.php` lists the files; changelog `[NEW]` line.

## Which rules apply?
- The legacy custom style layer is production (`pinegrap-verilmis-kararlar`): conversion never runs by itself, and the renderers (`get_catalog.php`, `get_shopping_cart.php`) are not touched.
- A converted page re-exported by the designer is normalised (indentation, attribute order, quoting); byte-identical output is not a goal unless the owner says so.

## What waits on this plan? (PHP region eval; owner 2026-10-19: no change now)
- `get_page_content.php` runs `eval('?>' . $content)` when `PHP_REGIONS` is on (default) and the assembled page holds `<?`. Page text and raw widget tokens are expanded before that line. `pg_defuse_php_tags()` (`core/php-regions-eval-and-content.md`) escapes content-level text, raw tokens and imported HTML; left is the architectural change: evaluate designer code only (style code, designer regions) before text and widgets are expanded.

## Open questions
- Theme designer (`theme_designer.php`) and `view_styles.php`: retire with the page designer or keep for classic sites?
- What happens to a classic style that uses a PHP template file (`style_file`)? Not convertible; it stays classic.

## Where to start?
- Not before R1-R3 of `legacy-retirement-prerequisites.md`. L1's placeholder converter can be built with R3 (designer regions need it); E3 (range map) is done.
