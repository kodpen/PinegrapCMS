---
name: shipping-address-widget
description: The Shipping Address and Arrival widget (first step of the classic multi-step checkout): handler, page row, recipient gate, controls by name, state switch, arrival date rows, what is left out.
module: widgets_shipping_address
---

## What is it?
- Kind `shipping_address_and_arrival` (`includes/fn/widgets_shipping_address.php`), the first of four widgets for the classic multi-step checkout ([[system-widget-missing-types]]). It posts to `shipping_address_and_arrival.php` with the classic field names; the handler's checks, address book write and redirects are unchanged.
- The cart sends the visitor here with `?ship_to_id=` (`next_page_id_with_shipping`). `pg_sw_page_type_widgets()` maps the classic type to it.

## Where are the page's settings?
- In the page's `shipping_address_and_arrival_pages` row, which the handler reads (`address_type`, `form`, `next_page_id`). A page of the classic type keeps its row; any other page gets one at render (`INSERT IGNORE`, `page_id` is unique), as the express order widget does.
- The handler takes a page of the classic type or one with a row (its page query).
- Widget settings `next_page_id`, `address_type`, `address_type_page_id` are written into the row only when the widget has the key (`pg_sa_page_row_changes()`); a next page of 0 changes nothing. So a converted classic page keeps what its own screen stored.

## Which recipient does it accept?
- The classic refusals (`pg_sa_recipient_problem()`): not found, order complete or exported, no order in the session, another order. On the live page each is `output_error()` with the classic words.
- No `ship_to_id` (or `from=control_panel`): the `no_recipient` block, no form. The editor's Preview (`edit`) draws the empty form instead.

## How are the controls filled?
- By name, as the account widgets do (`_pg_acct_fill_values()`): typed values after a refused post, else the address book entry of the same ship-to name (signed in, not ghost), else the `ship_tos` row; the default country.
- `state` is a text box and a pick list, both named `state`. The server unnames and hides the one the country does not use; `pg_sa_state_script()` switches them on a country change, moves the label and sets `required` on the zip code.
- `address_type` radios: dropped unless the row asks for them, then required. `shipping_same_as_billing`: dropped when not offered (`pg_sa_offers_same_as_billing()`).

## How are arrival dates drawn?
- One loop row per active date: the `arrival_date` radio gets the date id as value; `custom_arrival_date` is renamed `custom_arrival_date_<id>` and dropped for a date without a custom day (row flag `arrival_is_custom`). It is an `<input type="date">`: the handler reads ISO dates (`prepare_form_data_for_input()`).
- Static flags are applied with `arrival_is_custom => true`, so the row flag is decided per row.

## What is left out?
- The verified shipping addresses help box of the classic page.
- The staff edit grid around the custom shipping form; the form's rows are the classic markup in the `custom_shipping_form` section (only a classic page has `form = 1`).
