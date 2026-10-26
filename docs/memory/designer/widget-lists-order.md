---
name: widget-lists-order
description: Order view (receipt e-mail too) and my account lists (W10): recipients, items, parcels, steps, the member's orders; keys, tokens, where rows are read, the editor's starters, what is left
---

## Which lists exist?
- `pg_sw_list_order_sources()` (`includes/fn/widgets_lists_order.php`, engine [[widget-lists]]). Family `order` in `order_view` (the order receipt e-mail renders the same widget): `order.shipments` (ship_tos, `complete = 1`), `order.items`, `order.parcels` (`shipping_tracking_numbers`), `order.steps` (the order's steps; the timeline part draws them, [[widget-default-parts]]). Family `account` in `my_account`: `account.orders` (the order history's rows: complete, exported, cancelled, at most 50, newest first). None takes a field.
- Opt-in: a tree without these lists renders byte-identical; `__tracking_codes` and the `order_history` section stay.

## Which key does each read?
- The order lists read `__ship_to_key`: the static value `0` (the whole order), a recipient's id inside a recipients list, so an items or parcels list there lists that recipient's. Recipients and steps have rows under 0 only: inside a recipient they draw nothing. An item or parcel without a recipient (digital) is under 0 only.
- `account.orders` reads `account_id`; a list in a submission row lists the same orders.

## Which tokens does a row carry?
- The names the widgets already fill, so a row binds as the static part or the item loop does: recipients the `__shipping_*` tokens (no `__shipping_address_html`: list rows are escaped), `__arrival_date`, `__ship_to_key`, `__ship_to_item_count`; items the item loop's tokens without `__item_description` (HTML); parcels `__tracking_code`, `__tracking_link`, `__tracking_company_name`.
- Steps: `__step_label` (translated; a cancellation's without its reason), `__step_date`, `__step_kind` (created, paid, shipped, delivered, cancelled), `__step_note` (the reason), `__step_is_estimate` (a date still to come). Icons and colours are conditions on `__step_kind`, never a class.
- Orders: `__order_id`, `__order_no`, `__order_date` (site date format), `__order_total` (as the history prints it, plain text), `__order_status` (raw), `__order_is_cancelled`, `__order_url` (view order page `?id=`, `#` without one).
- Dates in rows are display strings: a format suffix on them is not reliable (as in the calendar lists).

## Where are the rows read?
- Order view: after the tree is drawn, `pg_sw_list_wanted('order')`; items, parcels (`ship_to_id` added to the tracking query) and steps (`kind`, `step_label`, `note`, `estimate` added to the timeline events) are in memory; the recipients only when a recipients list is drawn (one query). My account: `_pg_my_account_extras()` returns `orders` and `order_view_url`; published when the tree drew an account list.
- `pg_sw_list_publish()` and `pg_sw_list_source_rows()` treat every family but `form_record` as published rows: a new family adds no branch there.

## What does the editor give?
- Palette "Lists in a record": five items; `_sdOrderListStarter()` lands Bootstrap elements: recipients as cards with the recipient's items and parcels inside (two lists deep), items as a list group, parcels as a link shown with `show_if __tracking_link not_empty` and a plain number with `empty`, steps with an "Estimated" badge and the note, orders as list-group links with a "Cancelled" badge.
- Never in the order view's item loop (`_sdListPlaceOk()`) but for an item's lists ([[widget-default-parts-order-items]]); the band turns amber there and for recipients or steps inside a recipient (`_sdOrderListKeyless()`). Tests: `tests/widget_lists_order_test.php`, `test_widget_lists_editor_order`.

## What is not done yet?
- Payments (gift cards applied to the order) and the legacy account page's incomplete orders, reorder links and recipients: the widgets show no such data today, so they wait for W8's allow-list.
- A browser round (palette drop, Preview, a real receipt e-mail) is owed.
