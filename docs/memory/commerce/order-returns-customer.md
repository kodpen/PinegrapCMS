---
name: order-returns-customer
description: Customer side of returns in My Account's order view: the switch, __returns / __return_form and flags, order_return.php, messages, mail, bell, the block for older layouts, the hourly limit
---

## When can a customer ask?
- `ECOMMERCE_ORDER_RETURNS_ALLOWED` in data/config.php (off by default, like `ECOMMERCE_ORDER_CANCEL_ALLOWED`; documented in `data/config(default).php`) and the tables: `pg_returns_customer_enabled()`.
- Only the signed-in owner, on a `complete`/`exported` order, per delivery whose goods left and whose window is open, while something is left on a line: `pg_return_customer_view()`, the same rules `pg_return_create()` applies to source `customer`.
- Built with step 2 of `plans/customer-returns-flow.md` (PR #677). The panel screens are step 3: until they land staff get the bell and the activity log but no returns screen.

## What does the order view widget give?
- `__returns`: every request of the order with its status badge, lines, reason, both notes, the carrier and return code once approved, the tracking number, the refund due date and the customer's moves. `__return_form`: one form per delivery, a quantity box per line up to what is left, the reason, a note. Pre-built HTML from `pg_returns_list_html()` / `pg_return_form_html()` (pure, `tests/returns_test.php`).
- Flags `show_returns` (list or form), `can_request_return`, `has_returns`. The starter tree has a "Returns Card" in the left column after the notes.
- The list shows whatever the setting says; the form and the moves need it. Guests who just ordered and the receipt e-mail see nothing.
- A layout that places neither token (every design made before) gets `pg_return_block_html()` after the widget while the setting is on.

## What does order_return.php do?
- POST with the CSRF token: `action=request` (ship_to_id, `qty[<order item>]`, reason, note), `withdraw` and `shipped` (return_id, tracking_number). Owner check on `orders.user_id`; anybody else gets one answer whether or not the order exists; a return of another order is "not found"; a move the customer may no longer make is refused in plain words.
- Answers through liveform `order_receipt`, the widget's messages node, with `h()` on every message; goes back to `send_to` through `pg_safe_redirect_path()`.
- Ten actions an hour per customer (`waf_rate_exceeded('return|<user id>', 'order_return', 10, 3600)`): a withdrawn request can be made again, and each one mails and rings.

## What follows a request?
- A mail to the billing address (`pg_return_send_request_mail()`, through the mail queue when the general job runs). A failed mail does not undo the request.
- Bell and push: action `order_return` (title the order number, `order_total` requested or shipped, `form_id` the return), for `manage_ecommerce` (`includes/notifications.php`), linked to the order screen for now; external API subject `order`.
- Activity log lines for the request, the withdrawal and the parcel sent back.

## What is not here yet?
- The classic order view (`get_view_order_screen_content.php`, liveform `view_order`) shows no returns.
