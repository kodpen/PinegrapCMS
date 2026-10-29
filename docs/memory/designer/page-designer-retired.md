---
name: page-designer-retired
description: The legacy page designer (page_designer.php) retired in 2026.11.0: the owner's decision, where each of its jobs went, what was removed and kept, the rules that stay, what is left
---

## What did the owner decide?
- 2026-10-19: classic pages (a `style` row whose `style_code` is edited as text, regions in `cregion` / `dregion`) open in the visual designer, and the page designer (`page_designer.php`, `assets/js/page_designer.js`, `assets/css/page_designer.css`) goes for good once every page moves without loss in look and features. The code editor is the bridge: a converted page keeps a code view (`designer-code-editor-decisions.md`).
- Parity list (#2484): live-site browsing, Ctrl+D and Ctrl+E, the console pane, "Create Designer Region" / "Create Dynamic Region", the site-wide search and the System Tags dialog were dropped (an editor convenience is lost, site output does not change); the style's design files were built (P7, `classic-style-design-files.md`); the jump to the style, Ctrl+G back and the device sizes already had a twin.

## Where did each job go?
- A classic style: "Open in the visual designer" on `view_styles.php` (`classic-style-to-design.md`); its code as text: `edit_custom_style.php` ("Edit Page Style").
- Regions: `classic-regions-in-designer.md`. Design files and themes: their own screens.
- A page's custom layout: the designer's layout tab (`layout-code-tab.md`), else `edit_page_layout.php` (`layout-editor-classic.md`).
- Ctrl+G clicks the toolbar's `page_designer_button`, drawn for a `system` style only ("Open Style Designer", `pg_page_actions()`); on a custom style there is none, so the browser keeps the key.

## What went in L5, and what stays?
- Gone (PR #L5PR): the three files (`clean_up.php` deletes them on an updated site: an update deletes nothing), the toolbar's "Open Page Designer", the menu case, the help row, the session key `software.page_designer` and the api.php actions only its script called (`get_page`, `get_style`, `update_style`, `get_layout`, `update_layout`, `get_file`, `update_file`, `get_items_in_style`, `get_design_files`, `get_designer_region`, `get_dynamic_region(s)`, `update_designer_region`, `update_dynamic_region`, `create_design_region`, `create_dynamic_region`, `update_page_designer_properties`): such a call meets the general gate, then "Invalid action.".
- Kept: `get_styles` and `get_designer_regions`, which `migration.php` reads from another site beside `get_common_regions`, `get_folders`, `get_pages` and `test`.

## Which rules stay?
- The legacy custom style layer is production (`workflow/decisions-made-product.md`): conversion never runs by itself; the renderers and layout templates are not touched.
- A converted page re-exported by the designer is normalised (indentation, attribute order, quoting); byte-identical output is not a goal unless the owner says so.
- `theme_designer.php` and `view_styles.php` stay while any classic style exists (#2437).

## What is left?
- No panel field edits the root `title` a classic style brought (`classic-style-import.md`).
- An older cell-based system style is not offered for conversion (`pg_classic_style_refusal()`); a classic style built on a PHP template file is not convertible either. Both stay classic.
- Page regions inside the converted frame component cannot move into the page tree (`page-region-into-page-tree.md` refuses them inside a component).
- `get_page_content.php` still prints `software_page`, `software_style` and `software_theme` for roles 0-1; only `page_designer.js` read them. Left, as the renderer is not touched.
- PHP region eval re-ordering: `core/php-regions-eval-and-content.md`.

## Tests?
- `tests/page_designer_retired_test.php`; the layout paths in `designer_layout_retirement_test.php` and `designer_layout_code_test.php`.
