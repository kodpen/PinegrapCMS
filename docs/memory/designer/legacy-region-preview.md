---
name: legacy-region-preview
description: The canvas preview of a classic page's system region drawn by its custom layout (designer/legacy_region_preview): who sees it, the markers, the background render, read only, when it is asked again
---

## What does the canvas show?
- The page's own system region only: an unnamed `region` node of type `system` (`_sdLegacyRegionOwn()`) on a page in `_design.layoutPages` (custom layout, `pg_layout_custom_page_ids()`) keeps its label as a band and shows under it the region as the server draws it for the signed-in user, "as the site draws it, read only" (`_sdLegacyRegionAppend()` in `style_designer.js`).
- A named system region draws another page's region on the site, and the server marks only the unnamed one, so it keeps the plain placeholder (#1950; it used to show the page's own region a second time there). So do the other region types and a page off the list.
- DOM only, like the ghost records (`designer-widget-ghosts-and-edit-chips.md`): `inert`, `aria-hidden`, no `data-sd-id`, never in the tree JSON. A click selects the region node.
- Nothing of it may run in the editor, which shares the site's origin and holds the designer's session (#1950): `script`, `iframe`, `object` and `embed` are dropped (a srcdoc or `javascript:` frame runs even inside an inert box); ids, `on*` attributes and every attribute holding a `javascript:` address (`_sdLegacyRegionJsUrl()`: case and C0 controls or spaces do not count) are removed.

## Which state is drawn?
- The page's saved state: its stored tree, type settings and layout file (a hosted site's generated layout, as `render_layout()` draws there). A `system` node not saved yet has no region in the stored tree: the placeholder stays.
- Asked for once per page; asked again (the last answer stays meanwhile) after a design Save and after a save of the page's layout tab (`_sdLegacyRegionForget()`).

## How does the server cut the region out?
- `pg_layout_region_preview($page_id)` (`includes/fn/layouts.php`) renders the whole page with `get_page_content()` in `'preview'` mode, `$_GET['page']` set to the page name, as a background render (`pg_seo_rendering(true)`, `pg_error_throws(true)`, output buffered; everything put back in `finally`). An error gives ''.
- While `pg_layout_region_marking()` is on, `get_page_content.php` puts `<!--pg-layout-region-start-->`/`-end-->` around the page's own (primary) system region only; `pg_layout_region_cut()` takes the first pair. Off in every other request.
- The background render runs as the caller, with their session: the region is their view (account, cart, access). It sets no status code, counts no view and draws no edit chip.
- It is not free of writes: the classic renderers do for the caller what they do for any visitor, to the caller's own session and cart only. The cart and checkout steps keep their page ids in the session; the cart and the express order bring the caller's order up to date (special offer code, prices, offers, quantities held to the stock; `get_shopping_cart.php`, `get_express_order.php`).
- A custom form whose return type is a page, which the caller has submitted before, sends a redirect and exits (`get_custom_form_screen_content.php`): the answer is that redirect, not JSON, and the placeholder stays.

## Who may ask?
- api.php `designer` sub-action `legacy_region_preview`: the handler's token check, not in the content operators' list, then role 1 or better (the layout tab's right, `layout-code-tab.md`). Page cache: clean.

## Tests?
- `tests/designer_legacy_region_preview_test.php`.
