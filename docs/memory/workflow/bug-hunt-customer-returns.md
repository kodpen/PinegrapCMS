---
name: bug-hunt-customer-returns
description: Bug hunt of customer returns, the request and the refund (architect 2026-11-20, agent channel message 10330): faults fixed, issues opened, what was read clean
---

## What was the area?
- Customer returns, the request and the refund (architect 2026-11-20, agent channel message 10330): `includes/fn/returns.php`, `order_return.php`, `view_return.php`, `view_returns.php`, `add_return.php`, `return_reminder_job.php`. Not touched: `includes/fn/ecommerce.php`, `widgets_account.php`, `privacy_export.php`, `includes/api/`, the carrier label drivers, `add_erp_return.php`.

## What did it fix? (PR #10647 lists each with file and line)
- The daily reminder rang refund due and overdue for a shipped or received return of a cancelled order, and the Returns list, the return screen and My Account showed its refund date (red once late), though such a return is never refunded: `pg_return_refund_due($return, $order_status)` gives '' unless the order is complete or exported; the reminder, `pg_returns_panel_rows()` (`order_status`), `view_return.php` and `pg_return_customer_view()` pass it.
- The My Account return form printed "Already in a return" on a gift card line: `pg_return_customer_view()` hands `gift_card` on each line, and `pg_return_form_html()` prints "Gift cards cannot be returned".
- #10651 (K2's reviews hunt, added by coordinator K1): the review request e-mail went out after refunded returns took back every unit of the order. `pg_return_refund()` calls `pg_review_request_cancel()` when `pg_return_order_fully_refunded()` (pure part `pg_return_order_refunded_whole()`) says nothing is left; a partly refunded order keeps its request.
- Tests: `tests/returns_hunt_test.php`.

## What was opened as an issue?
- #10671: an order screen refund by lines takes the lines' reward points back, and a 0 recorded refund of their return takes them again (needs a decision; reaches `view_order.php`).
- #10672: the designer order view's return form still says "Already in a return" on a gift card line (`widgets_lists_order.php`, `widgets_parts_forms.php`, outside the area).

## What was read clean?
- The withdrawal window and its estimate, goods left, the timing rules and the repeat before them; held quantities and the line check (ids sent twice overwrite, never add).
- Also clean: the create lock and the re-check under it; status_was and the conditional UPDATE; the refund's lock, split, revert on refusal and credit failure, unclear answers; amount parsing and the estimate's `round()`; the cancelled order's refund refusal; the gift card part; the hourly limit (owners only, label downloads included); the reminder's once-only stamps; the label download's ownership check; bulk approve.
- Left as decided: a return of a partly shipped line may ask for every unit (`order-returns-scope.md`).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/order-returns.md
