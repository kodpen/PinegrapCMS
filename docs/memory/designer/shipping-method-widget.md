---
name: shipping-method-widget
description: The Shipping Method widget (second step of the classic multi-step checkout): handler, next page, methods from get_shipping_methods(), the chosen method, items section, what is left out.
---

## What is it?
- Kind `shipping_method` (`includes/fn/widgets_shipping_method.php`), the second of the four classic checkout widgets ([[system-widget-missing-types]]). It posts `shipping_method`, `page_id`, `next_page_id`, `ship_to_id` to `shipping_method.php`; the handler checks the method again (`check_shipping_method()`), stores its cost and redirects (next recipient, express order, billing or order preview).
- The handler has no page type check, so a designed page needs no page row. `pg_sw_page_type_widgets()` maps the classic type to it.

## Where does the next page come from?
- The widget's `next_page_id`, else the classic page's `shipping_method_pages.next_page_id`; it is posted and stored as the session's billing information page.

## Which recipient does it accept?
- The same refusals as the shipping address widget (`pg_sa_recipient_problem()`, `output_error()` on the live page); no `ship_to_id`: the `no_recipient` block. The editor's Preview has no recipient either: methods need one.

## Where do the methods come from?
- `get_shipping_methods()` (shipping.php), as the express order widget and api.php use it, with the recipient's stored address and `arrival_date` (ISO, `arrival_date_id` 0 so the stored date is used as is). Cheapest first; `cost_info` is the classic price HTML.
- An error answer: no methods, flag `no_shipping_methods`, `__no_shipping_methods_message` is the site's "no shipping methods" message when set, else the function's own words.
- The chosen radio (`pg_sm_chosen_method()`): the posted method, else the stored one while offered, else the only one.

## What is a section, not designable?
- The recipient's items (`recipient_items`): name, quantity, amount; a product that cannot go to the address carries the restriction message and its remove link. A designable list can follow with the product list engine (W9).

## What is left out?
- The currency switcher the classic custom layout offers.
