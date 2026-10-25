---
name: order-returns-classic-view
description: Returns in the classic My Account order view: listed for the owner whatever the switch, form and moves while it is on, the answer via liveform order_receipt, Bootstrap 3 markup
---

## Which page is it?
- A legacy page of type `view order` (`get_view_order_screen_content.php`), linked from the legacy My Account page and, when no designer order page exists, from the My Account widget. It is not the panel's `view_order.php`. The designer order view widget is order-returns-customer.md.

## What does it show, and to whom?
- Only to the signed-in account that owns the order: `pg_return_classic_visible(USER_ID, orders.user_id, email)`, the `USER_ID` rule `order_return.php` applies. The page's own check (user looked up by `sessionusername`) still runs first. Never in a page drawn as an e-mail (`pg_sw_render_context()['email']`); guests are sent to sign in by `get_page.php`.
- The order's returns always when it has some (decided 2026-10-25): a return staff or an app recorded is visible with the switch off, read-only. The request form and the customer's moves (withdraw, "I have sent it") only while `ECOMMERCE_ORDER_RETURNS_ALLOWED` is on, under `pg_return_customer_view()`'s rules. `pg_return_classic_view_html()` (pure) also drops the moves when the view says the switch is off.
- An order without returns, or with nothing to ask for, draws byte for byte as before.

## How does the answer come back?
- The forms post to `order_return.php` with `send_to` = `REQUEST_URL`; it answers through liveform `order_receipt` and redirects back. The page reads that liveform, prints it at the top of `div.software_order_view` and removes it.
- Not liveform `view_order`: seven panel order screens share that name (customer and staff messages would cross), and the system layout never prints it. `order_return.php` is unchanged apart from its docblock.

## Where does the block go, and how is it drawn?
- After the payment section in the system layout, after the rendered template in a custom one: appended outside the layout and after `$form->prepare()`, which would rewrite the forms' hidden fields (`send_to`). No template edit, no tokens: a modified layout file gets it too.
- `pg_return_classic_block_html()`: the page's `returns heading` / `returns data` idiom, no card and no `.container` (classic themes ship Bootstrap 3; Bootstrap 5 is loaded only for visual_designer styles). The list and form are the widget's builders, unchanged; on Bootstrap 3 they degrade but stay usable, and one scoped `<style>` rule keeps the requests apart.
- Dates through `pg_return_classic_date()`: `get_absolute_time()` with `format => plain_text`, because the builders escape dates and the default `<time>` element would show as text.

## What is known not to work?
- A `view order` page that also carries an order view widget draws two order views; whichever renders first prints the answer.
- `order_history_view_order.php` without a `view order` page ends in a missing template (older bug).

## Tests?
- `tests/returns_test.php` (`test_returns_classic_*`, `test_returns_block_html`), `tests/order_view_screen_test.php` (`test_order_view_screen_returns`, source order). Sandbox (Bootstrap 3 turkish_default, both layouts): request, repeat, withdraw, sent, a refusal, another account's order, a guest, switch off, e-mail render, no tables; the widget's output for the same orders unchanged.
