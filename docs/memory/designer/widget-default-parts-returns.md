---
name: widget-default-parts-returns
description: The order view's returns list part (W16, #2067): order.returns and order.return_lines lists replacing __returns, tokens, badges, where the customer's moves are
---

## What does the part draw?
- `pg_sw_part_order_returns_list()` (`includes/fn/widgets_parts.php`): `div.pg-ov-returns`, shown by `eo_visible_if has_returns`, holding an `order.returns` list of `div.pg-ov-return.border.rounded.p-3.mb-3`, each: the heading row (`__return_title`, `__return_asked` when not empty, one badge per status by `__return_status eq`, classes from `pg_return_badge_class()` baked in, text `__return_status_label`); `ul.small.mb-2.ps-3` (`__return_has_lines`) of an `order.return_lines` list of `li` "^^__return_line_name^^ × ^^__return_line_qty^^"; detail rows `div.small.mb-1` (label `span.text-muted` "Reason:", value span) for reason, both notes, tracking number, refund date; `div.alert.alert-info` (`role=status`, `__return_status eq approved`) with `__return_how` and `strong` `__return_code` when not empty; then a div of `__return_actions_html`; then `div.mt-2` (`__return_has_label` not empty) with `a.btn.btn-sm.btn-outline-primary` "Download return label", href bound to `__return_label_url` (#3268).
- Same words, classes and forms as `pg_returns_list_html()` (owner option B). The classic page (`pg_return_classic_view_html()`) still uses that function; it is unchanged, its sentence and forms now from `pg_return_how()` and `pg_return_actions_html()` (`returns.php`), which the rows share.

## Where do the rows come from?
- `pg_sw_list_order_return_rows($returns, $form)` (`widgets_lists_order.php`, pure): `order.returns` under key 0 (`__ship_to_key`, the whole order), `order.return_lines` under the return id (`__return_id`). The order view builds them from `pg_return_customer_view()` (owner signed in, never in an e-mail) and publishes them with the other order lists; `has_returns` is "there are rows".
- `__return_how` is set only for an approved return; `__return_code` is the carrier code always.
- `__return_label_url` / `__return_has_label`: set only while the return label is offered (`label_url` of `pg_return_present()`, filled by `pg_return_customer_view()`; `commerce/shipment-label-returns.md`).
- Raw (`pg_sw_raw_token_keys()`, editor `SW_ATTR_MARKUP`): `__return_customer_note_html`, `__return_decision_note_html` (escaped, line breaks kept), `__return_actions_html`. A condition reads the plain `__return_customer_note` / `__return_decision_note`.

## Where are the customer's moves?
- The moves part ([[widget-default-parts-order-forms]]) in each row, in place of `__return_actions_html` (still filled for a token in a longer text), shown by `__return_can_mark_sent` / `__return_can_withdraw`.
- In a list row every id gets the row's suffix (`pg_sw_uniquify_row_ids()`): the tracking box is `pg-ov-return-tracking-<id>_pgw…`, its label's `for` with it. No script reads that id; names and hidden fields are as before.

## How does an old layout get it?
- A node bound to `__returns` (or a text that is the token alone) becomes the host (`pg_sw_default_part_tokens()`, editor `SW_DEFAULT_PARTS`). The returns card added after an order view (`pg_sw_part_order_returns()`) holds the part in a div, then the form; it is placed by `__returns`, `__return_form`, an `order.returns` or `order.return_deliveries` list; the form is the return form part ([[widget-default-parts-order-forms]]). The starter's returns card holds the part.
- The order view no longer fills `__returns`; the editor no longer offers it.

## Editor rules?
- `_sdOrderItemListKey()`: `order.return_lines` reads its key from an `order.returns` row; outside one the band is amber. `order.returns` inside a recipients list is amber (whole order only).

## Tests?
- `tests/widget_default_parts_returns_test.php`: rows; old list vs part by words, classes, forms (row suffix taken out), escaping; placement and the card; raw keys; the editor twin under Node.
