---
name: order-returns-staff-request
description: Staff record a return request on the panel (add_return.php, source panel): entry points, form, timing warnings, what follows (audit, log, opt-in mail, no bell), repeat, refusals
---

## Where do staff record a request?
- `add_return.php?order_id=` (gate as `view_return.php`: `validate_user()`, `validate_ecommerce_access()`, `ECOMMERCE`). For a customer who asked by phone, e-mail or in the shop.
- Entry points: the order screen's Returns card (`pg_returns_panel_card()`), drawn also for an order without returns when `pg_return_recordable()` (complete/exported, something left on a line sent to an address); and **Record a Return** in the `view_returns.php` toolbar, which opens an order number step (`orders.order_number`, then the id; `#` and spaces allowed, `pg_return_order_number_input()`; a cart is refused there).

## What does the form hold?
- One delivery at a time (`pg_return_staff_deliveries()`: only deliveries with something left; a toolbar select switches when there are several; an unknown `ship_to_id` falls back to the first).
- A box per line named `qty_<order item id>` (`pg_return_staff_quantities()`): liveform refills flat names, never `qty[<id>]`.
- The reason select from `pg_return_reason_options()`: a liveform select takes label => value and prints both raw, so labels are escaped and the code is posted.
- The customer's words go to `customer_note` (the customer sees them on the order page and in the mail; needed with "other").
- The request-received mail box is off by default and drawn only when the order has an address with `@` and the site a sender (the condition of `pg_return_mail_context()`).

## Are staff held to the customer's timing rules?
- No (`pg_return_customer_rules()` is false for panel). One rule for both sides: `pg_return_rule_breach($state)` (pure: `not_sent`, `delivery_date_unknown`, `window_closed` or ''). `pg_return_create_check()` refuses a customer on it with the same codes and sentences as before; the form shows it as an alert above the form and a confirm on the button, and records anyway.

## What follows a new request?
- `pg_return_staff_record()`: `pg_return_create()` with source `panel` and the person as `requested_by`; `pg_audit('create', 'order_return', …)` over `pg_return_audit_create_fields()`; one activity log line; the request mail only when ticked (`mailed` true/false, null when not asked; a failed mail never undoes the request).
- No bell or push: the person who would be told is the one who recorded it, push has no actor filter, and the bell says a customer asked. `order.return.requested` (payload `source` panel) and the Workspace trigger come from `pg_return_create()`.
- Staff land on `view_return.php?id=` with a notice (`pg_return_staff_notice()`).

## What does a double click do?
- The repeat of `pg_return_create()` (same person, order, delivery, reason, note, lines within 60 s) lands on the first request; nothing else follows and the notice says so. A second submit that only ticks the mail box is such a repeat too: no mail.

## How is a refusal answered?
- By code, never by sentence (`pg_return_staff_refusal()`): reason, note (staff wording, not the customer's), lines, busy and a wrong delivery go back to the form with the field marked and what was typed; `order_missing` goes to the returns list, `order_not_returnable` and `not_installed` to the order screen.
- `remove_form()` comes before every redirect but the one back to the form, and before "Record not found.": a customer's words never refill another order's form.

## Tests?
- `tests/returns_staff_request_test.php` (helpers, the check's use of the rule, wrapper and screen source order), the card in `tests/returns_test.php`. Sandbox: one and two deliveries, late, unsent, undated, double click, refill, mail on/off/failed, busy lock, gate.
