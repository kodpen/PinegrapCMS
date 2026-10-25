---
name: catalog-stock-notify-form
description: The back-in-stock form of the catalog item view: stock_notify_email and stock_notify bindings, the can_notify flag and its variant switch, the hidden form after the widget, the starter block
---

## What does a designer place?
- An `<input>` bound to `stock_notify_email` (Field binding) and a button (btn component or `<button>`) bound to `stock_notify` (Action, offered on a real button only), usually in a block with "Show only when" `can_notify`. The item view starter carries such a block under the add-to-cart row (PR #1664); the store, boutique and bookshop product pages open with the starter (`'tree' => 'starter'`).
- `can_notify` (`SW_VISIBILITY_FLAGS.catalog_item_view`): the product tracks stock, none is left, no back order (`pg_stock_notify_product_out()`, the rule of `__out_of_stock`), and `stock_notify` has its table. The requests and the mail: [[back-in-stock-notices]].

## What does the server do with them?
- `_pg_catalog_stock_notify_control()` (binding pass): the field becomes `type="email" name="email_address" autocomplete="email" maxlength="100" required`, filled with `USER_EMAIL_ADDRESS` when signed in and the design gave no value; a component button becomes a submit `<button>` ("Notify me" for a boilerplate caption), a `<button>` gets `type="submit"`. Both get `form="pg-stock-notify-<n>"`.
- A control with no "Show only when" of its own takes `can_notify`, so it never shows for a product that can be bought; a flag the designer set is kept.
- `_pg_catalog_stock_notify_dropped()`: no table (no form id) or a node that cannot be the control (a link bound as the button, a select bound as the field) is dropped. A switched-off feature draws nothing.

## Where is the form?
- After the add-to-cart `</form>` (a form cannot nest): `<form id="pg-stock-notify-<n>" action="…/stock_notify.php" method="post" class="d-none">` with `get_token_field()`, `product_id`, `send_to` (the request URI). Printed only when the HTML holds a control naming it. The add-to-cart form then carries `data-pg-stock-notify="<id>"`.
- The field belongs to the other form (`form=""`), so its `required` never blocks Add to Cart and Enter in it posts the request. The answer comes back in the `catalog_detail` liveform, which the widget's Messages node shows.

## What happens on a select group's page?
- When the variants differ in stock (`_pg_catalog_stock_states_differ()`: some `out_of_stock` '1', some not), `can_notify` is switched like the rating: both states kept, marked `data-pg-variant-flag="can_notify"`, the one that is off `hidden` (`_pg_catalog_variant_flags_keep($html, $flags, $switched)`, [[catalog-rating-tokens]]).
- `applyVariant()` in `pg_civ_variants.js` (version stamp 6) hides or shows them from the variant's `out_of_stock` and moves the request form's `product_id` to the variant picked.

## How is it tested?
- `tests/stock_notify_item_view_test.php`: the binding pass on fake trees (attributes, the account's address, flags, drops), the stock switch, the renderer's pins (flag, form, token, placement), the variant script and the designer's offer and starter.
