---
name: legacy-set
description: A set of classic styles on the move report (LG4, #8776): the set key, who moves alone, mobile styles, folder pages, Head code, body classes, the set's dry run
module: legacy_to_designer, legacy_to_designer_plan
---

## What is a set?
- Classic styles whose code loads the same external stylesheets and scripts in the same order, with the same `collection` and `theme_id`: one Legacy design holds them (`workflow/decisions-made-product-43.md`).
- `pg_ltd_style_refs()` reads `<link rel="stylesheet" href>` and `<script src>` outside comments, entities decoded, `{path}`/`{software_directory}` filled (LG2), each address once. `pg_ltd_set_key()` hashes them with collection and theme (16 hex).
- Inline `<style>`/`<script>` blocks do not count: a block every style with pages has is a design-wide asset row, any other is limited to its style's pages (`pages`, `pg_ltd_set_assets()`).
- Layout override and sharing position are not in the key: the design holds one value, the set's most common (the first on a tie, `pg_ltd_set_setting()`); the dry run names each style whose classic pages had another (sharing only while Social Networking is on).

## Who moves alone?
- `pg_ltd_sets()`'s `alone`: a style the designer refuses (`blocked`) and a style whose key no other style has (`single`). Each moves as before, one design per style.

## How do mobile styles, folder pages, Head code and body classes fall?
- A mobile style joins its set: the move leaves the classic rows, so a style named in `folder.mobile_style_id` or `page.mobile_style_id` keeps serving phones. While the Mobile setting is on, the screen says so for the style and the dry run counts the set's pages that have a mobile style; with it off nothing is said, as phones get the desktop style (`initialize_device_type()`).
- Folder pages (`page_style = 0`) get the design as their own style; the folder keeps the classic style.
- `style_head` is not carried: `get_page_content.php` prints it only for `system` styles, so a classic custom style never printed it. The dry run says so.
- `additional_body_classes` go on that style's own pages, as in the single move.

## What do the screen and the dry run show?
- `legacy_to_designer.php`: a `tbody.ltd-set` per set (checkbox `sets[]`, name "<first style> set", styles, pages, theme file, the addresses folded), largest first, then "Styles that move alone".
- `pg_ltd_plan($style_ids, $set_keys)` → `pg_ltd_plan_set()` (pure): the one design, theme file, every-page files and settings, then each style folded with its own writes; a style opens by itself when skipped, with a setting change, a warning or a held page. A common or designer region is made once per set, under the first style placing it. Writes: 1 for the design + each style's less its flip.
- A style ticked alone inside a chosen set is shown in the set. Move to the visual designer moves the sets and the styles chosen alone (`legacy-set-run.md`); a set moved before is skipped as `no_pages`.

## Tests?
- `tests/legacy_set_test.php`: refs, key, grouping, the setting majority, assets, the set plan, regions made once, the HTML, the screen's wiring.
- Sandbox (turkish_default, 50 classic styles): sets home-2 (40 styles, 146 pages, 6 CSS, 13 JS, 290 writes), auto-dialog, emailer-inline, home-1; 4 alone. Light, dark, phone width, keyboard; no PHP error.
