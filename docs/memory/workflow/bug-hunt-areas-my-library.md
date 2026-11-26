---
name: bug-hunt-areas-my-library
description: Bug hunt of the designer's My library (2026-11-26): an item saved, inserted as a copy, managed, exported and imported as a file; what was fixed, what read clean, what was left
module: designer_library, designer_library_io
---

## What was the area?
- The designer's My library (ML1 to ML4): `includes/fn/designer_library.php`, `includes/fn/designer_library_io.php`, the parts `libraryfile` and `librarymanage`, and the library actions in `includes/panel/designer.php`.

## What did it fix?
- Import: `pg_library_text_problem()` read `on…=` case-sensitively; `<img src=x ONERROR=alert(1)>` passed and ran where the copy was added.
- Import, tags read short: `<img alt=">" onerror=…>` (a quoted `>`) and a tag left open at a text's end passed (`_pg_library_markup_tags()`).
- Import: `href="java&#115cript:…"` passed: `html_entity_decode()` needs the `;` a browser does not (`_pg_library_entity_decode()`).
- Import, style: a quoted `url()` holding `)` or the other quote, and CSS escapes (`\64 ata:`), passed.
- Import, SVG animation: `<animate attributeName="href" values="javascript:…">` and `<set … to="javascript:…">` passed; `to`, `from`, `by`, `values` are now read as addresses.
- Insert, a site's own component id: a hand-made file whose tree placed `sharedId: 9` was inserted pointing at this site's row 9. `pg_library_item_into_design()` now clears it and marks the placement `['shared']`.
- Insert, product groups and calendars: `product_group_id`, `quick_add_product_group_id`, `catalog_listing_root_group_id` and `calendar_ids` were no reference kind, so another site's ids pointed at this site's records. They are kinds `product_group` and `calendar`, carried by name like pages.
- Insert, menus: a menu widget's `menu_name` the site lacks stayed as written. Architect (2026-11-26): a fault against the rule. Kind `menu`, matched by name (case aside): kept when the site has it, else `''` and marked `menu`.
- Tests: `*_hunt_*` in `tests/designer_library_io_test.php` and `tests/designer_library_test.php`. An item saved before the fix has no name for these kinds: its product group or calendar is cleared and marked on insert.

## What was left?
- `_sdLibUnbound()` and the insert line's words ("choose a form, collection or group") do not know the new kinds; `style_designer.src.js` and `tr/a.json` were held by other open PRs.
- So the amber mark of a cleared `product_group`, `calendar`, `menu` or `shared` reference stays after a record is chosen again.
- Two saves of one name at the same moment can pick the same file name (`check_name_availability()` then the write, no lock); the second overwrites the first file.
- `image-set("…")` strings are not read as addresses (no script runs from CSS).

## What was left as it is?
- A picture the other site lacks is kept as written. Architect (2026-11-26): a picture is a file, not a record; its address is the only trace of which one was meant, and the file put there repairs it; an address on another host or a token cannot be checked. What a file does about its pictures is asked of the owner.

## What read clean?
- Rights and CSRF: every library call goes through `pg_panel_designer()` with `validate_token()`; content roles get only `library_list` and `library_prepare` (the allow-list), and each call asks `pg_library_may()`; `make=1` asks `save`.
- File system: the file name is `library-` and `[a-z0-9-]` only, a name in use gets `-2`…; `_pg_library_read_file()` refuses a path (`basename()`).
- Limits: 1 MB before `json_decode()`; trees past 80 levels, settings past 40 refused; broken JSON is "not a library file".
- What an item carries: no key, address list or connection in widget settings; notes and editor ids dropped.
- Two people: a delete or rename of an item the other already deleted answers "The library item was not found." and the window draws the list again.
- Names are escaped in both windows.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-my-library-overview.md, docs/memory/designer/designer-library-import-checks.md
