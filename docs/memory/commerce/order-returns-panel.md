---
name: order-returns-panel
description: Panel side of returns (PR #808): list and bulk approve, one return's moves, pg_return_staff_move() (audit, log, mail), decision mails, suggested return carrier and code
---

## Which screens are there?
- `view_returns.php` (Orders menu › Returns): newest request first, at most `PG_RETURN_PANEL_LIMIT` (1000) rows; status filter `pg_filter_select()`, default `open` (requested, approved, shipped, received: `pg_return_open_statuses()`), one status or `all`, kept in `$_SESSION['software']['ecommerce']['view_returns']`. Only `requested` rows have a checkbox; **Approve Selected** (`bulk=approve`, POST to itself) runs `pg_returns_bulk_approve()`.
- `view_return.php?id=`: lines, reason, both notes, the forms of `pg_return_staff_actions()` (every staff move but `refunded`, which has its own card: commerce/order-returns-refund.md); side column with the stamps and who, order, customer, parcels, delivery date (known or estimated), withdrawal deadline and whether the request came in time, refund due date.
- Gate on both: `validate_user()` + `validate_ecommerce_access()` + `ECOMMERCE`. POSTs check the token; a refusal keeps what was typed (`add_fields_to_session()` only then).
- Order screen: `pg_returns_panel_card()` under Legal Documents. Bell/push row `order_return` opens `view_return.php?id=<form_id>`.

## What does a staff move do?
- `pg_return_staff_move($id, $to, $options, $user_id)`: refuses a rejection without a note (the customer is told why); `pg_return_move()` with `by` staff; `pg_audit('update', 'order_return', id, …)` over `pg_return_audit_fields()` (status, decision_note, carrier, carrier_code, tracking_number; no time stamps); one activity log line per target; the decision mail on approved/rejected. A failed mail never undoes the move; the screen says the mail could not be sent.
- Sent back by staff: tracking number, carrier only when chosen (empty keeps the approval's). The refund deadline counts from that stamp.
- Approved and received are announced by `pg_return_move()` itself (`order.return.approved` / `.received`, one per request of a bulk approval); rejected, shipped and closed are not (commerce/order-returns-api.md).

## Which carrier and code does approval offer?
- `pg_return_approval_defaults()`: the delivery's parcel carrier (`shipping_tracking_numbers.carrier`, else recognised from the method code with `pg_shipping_carrier()`), else `pg_shipping_default_carrier()`, else the latest approval's carrier; the code is the one given with the latest approval for that carrier (a contracted return code repeats). There is no setting for a fixed return carrier (it is written into the contract template); bulk approve uses the same suggestion and no note.

## What do the mails say?
- `pg_return_decision_mail_body()`: approved → carrier and code (or "you can send the goods back now"), the shop's note, and "tell us on your order page" when `pg_returns_customer_enabled()`; rejected → the shop's note, no carrier or code. Sent by `pg_return_send_decision_mail()` through the mail queue, sharing `pg_return_mail_context()` with the request mail.

## What is not here yet?
- Staff recording a request from the panel (source `panel`). The refund, the ERP hand-off and the reminder job: commerce/order-returns-refund.md; the API, the webhooks and the Workspace trigger: commerce/order-returns-api.md.

## Tests?
- `tests/returns_test.php` (filter, defaults, actions, audit fields, refund due, parcel delivery, mail body, order card). The screens ran on a `tools/setup_sandbox.sh` sandbox (PR #808 body).
