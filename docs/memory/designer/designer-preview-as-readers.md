---
name: designer-preview-as-readers
description: What each widget renderer reads of a "Preview as" case (PW2b): Empty, Long, Sold out, Error; the readers, what is not read, the cart write kept out of the Preview
module: designer_preview_render
---

## What do the renderers read of the case?
- Readers in `designer_preview_render.php`; outside a Preview widget each gives back what the renderer had, so a live visit draws as before: `pg_preview_as_empty_where()` (' AND (TRUE = FALSE) ' for Empty), `pg_preview_as_order($flag, $expr)` (an ORDER BY head for Long or Sold out), `pg_preview_as_rows($rows, $keys)` (none for Empty, most characters first for Long, stable).
- Empty: form list and catalog listing by their statements; search results (nothing searched), calendar, gallery, folder view, comments, recent comments, shopping cart and express order (no order) by their rows. Long: form list (most `form_data` characters), catalog (name plus full description), search, calendar, gallery, folder view, cart lines. Sold out: the catalog listing puts unbuyable products first.
- Error: `pg_preview_as_form_error($lf, <liveform>)` marks the first required field of login, forgot password, sign-up, affiliate sign-up, billing and shipping with their handlers' message; `pg_preview_as_custom_form_error()` the custom form's first required field a visitor fills in (`pg_cf_required_message()`). The mark is in the session, put back after the widget.
- Not read: Long on comments (a thread keeps its order), order preview (draws nothing in a background render), the order form (no plain required field).
- The cart's recurring defaults (`UPDATE order_items`) are not written in a background render (PW2b, #11632); before, the Preview wrote them to the operator's own cart.
- Since the Preview as hunt the shopping cart and the express order ask `pg_preview_as_may_write()` (false in a background render) before `initialize_order()`, `check_inventory()`, `update_order_item_prices()`, `apply_offers_to_cart()` and the express order's tax pass: in a Preview they read the session's order as it stands, with no offer banners. Before, each Preview drawing wrote the operator's own cart (owner, prices, offers, stock cuts, billing country).
- The express order's `express_order_pages` sync (the row, next page, receipt e-mail) runs on a visit only: the Preview draws the editor's unsaved settings and wrote them to the live checkout.

## Is the visitor read?
- Yes, since PW2c (#11728): through `pg_preview_as_viewer()`, as `designer-preview-as-viewer.md` says.
