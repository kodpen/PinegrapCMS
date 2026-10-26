---
name: legacy-region-preview
description: The canvas preview of a classic page's system region drawn by its custom layout (designer/legacy_region_preview): who sees it, the markers, the background render, read only, when it is asked again
---

## What does the canvas show?
- A `region` node of type `system` on a page in `_design.layoutPages` (custom layout, `pg_layout_custom_page_ids()`) keeps its label as a band and shows under it the region as the server draws it for the signed-in user, "as the site draws it, read only" (`_sdLegacyRegionAppend()` in `style_designer.js`).
- DOM only, like the ghost records (`designer-widget-ghosts-and-edit-chips.md`): `inert`, `aria-hidden`, no `data-sd-id`, scripts, `on*` attributes and ids removed, never in the tree JSON. A click selects the region node.
- Every other system region, and a page off the list, keeps the plain placeholder.

## Which state is drawn?
- The page's saved state: its stored tree, type settings and layout file (a hosted site's generated layout, as `render_layout()` draws there). A `system` node not saved yet has no region in the stored tree: the placeholder stays.
- Asked for once per page; asked again (the last answer stays meanwhile) after a design Save and after a save of the page's layout tab (`_sdLegacyRegionForget()`).

## How does the server cut the region out?
- `pg_layout_region_preview($page_id)` (`includes/fn/layouts.php`) renders the whole page with `get_page_content()` in `'preview'` mode, `$_GET['page']` set to the page name, as a background render (`pg_seo_rendering(true)`, `pg_error_throws(true)`, output buffered; everything put back in `finally`). An error gives ''.
- While `pg_layout_region_marking()` is on, `get_page_content.php` puts `<!--pg-layout-region-start-->`/`-end-->` around the page's own (primary) system region only; `pg_layout_region_cut()` takes the first pair. Off in every other request.
- The background render reads the session, so the region is the signed-in user's view (their account, cart, access), but writes nothing: no status code, redirect, view count or edit chip.

## Who may ask?
- api.php `designer` sub-action `legacy_region_preview`: the handler's token check, not in the content operators' list, then role 1 or better (the layout tab's right, `layout-code-tab.md`). Page cache: clean.

## Tests?
- `tests/designer_legacy_region_preview_test.php`.
