---
name: order-returns-customer
description: Customer side of returns in My Account's order view: the switch, __returns / __return_form and flags, order_return.php, messages, mail, bell, the block for older layouts, the hourly limit
---

## When can a customer ask?
- `ECOMMERCE_ORDER_RETURNS_ALLOWED` in data/config.php (off by default, like `ECOMMERCE_ORDER_CANCEL_ALLOWED`; documented in `data/config(default).php`) and the tables: `pg_returns_customer_enabled()`.
- Only the signed-in owner, on a `complete`/`exported` order, per delivery whose goods left and whose window is open, while something is left on a line: `pg_return_customer_view()`, the same rules `pg_return_create()` applies to source `customer`.
- Built with PR #677 (scope: order-returns-scope.md). Staff answer the requests on the panel (PR #808): commerce/order-returns-panel.md.

## What does the order view widget give?
- `__returns`: every request of the order with its status badge, lines, reason, both notes, the carrier and return code once approved, the tracking number, the refund due date and the customer's moves. `__return_form`: one form per delivery, a quantity box per line up to what is left, the reason, a note. Pre-built HTML from `pg_returns_list_html()` / `pg_return_form_html()` (pure, `tests/returns_test.php`).
- Flags `show_returns` (list or form), `can_request_return`, `has_returns`. The starter tree has a "Returns Card" in the left column after the notes.
- The list shows whatever the setting says; the form and the moves need it. Guests who just ordered and the receipt e-mail see nothing.
- A layout that places neither token (every design made before) gets `pg_return_block_html()` after the widget while the setting is on.

## What does order_return.php do?
- POST with the CSRF token: `action=request` (ship_to_id, `qty[<order item>]`, reason, note), `withdraw` and `shipped` (return_id, tracking_number). Owner check on `orders.user_id`; anybody else gets one answer whether or not the order exists; a return of another order is "not found"; a move the customer may no longer make is refused in plain words.
- Answers through liveform `order_receipt`, the widget's messages node, with `h()` on every message; goes back to `send_to` through `pg_safe_redirect_path()`.
- An emptied quantity box (`qty[<id>]=`) counts as 0 in `pg_return_check_lines()`; it used to refuse the whole request with "İade edilecek adedi tam sayı olarak girin." (#857).
- Ten actions an hour per customer (`waf_rate_exceeded('return|<user id>', 'order_return', 10, 3600)`): a withdrawn request can be made again, and each one mails and rings.

## What does the same form sent twice do?
- Symptom (#856): two clicks on "İade talep et" saved two identical requests (two mails, two bells, the second unit "Zaten iadede"). Inside the order lock and before the quantity check, `pg_return_create()` now looks for a `requested` return of the same order, delivery, source and person from the last `PG_RETURN_REPEAT_SECONDS` (60) with the same reason, note and lines (`pg_return_repeat_of()`, pure); found, it answers `ok` with that id and `repeat => true`, and `order_return.php` only says "received", without mail, bell or log.

## What follows a request?
- A mail to the billing address (`pg_return_send_request_mail()`, through the mail queue when the general job runs). A failed mail does not undo the request.
- Bell and push: action `order_return` (title the order number, `order_total` requested or shipped, `form_id` the return), for `manage_ecommerce` (`includes/notifications.php`), opening the return's screen (`view_return.php?id=<form_id>`; a row without one opens the order); external API subject `order`.
- Activity log lines for the request, the withdrawal and the parcel sent back.

## And the classic order view?
- The legacy `view order` page shows the same list and form (PR #1617): commerce/order-returns-classic-view.md.
