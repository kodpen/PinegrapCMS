---
name: api-returns
description: GET /returns, GET /returns/{id}, POST /returns/{id} (approve, reject, ship, receive, close, refund) through the return screen's write functions, the action check, codes, events
module: returns
---

## Which routes are there?
- `returns.list` `GET /returns` (`orders:read`): every order's returns, newest first, cursor on the id (`id < last`), filters `status` (one status or `open`: `pg_return_open_statuses()`, the panel's default) and `order_id`. Lines of the page in one query (`pg_return_items_of()`), withdrawal windows per order (`api_return_windows_of_orders()`: a `ship_to_id` belongs to its order). Empty before the tables exist.
- `returns.get` `GET /returns/{id}`, `returns.update` `POST /returns/{id}` (`orders:write`, rehearsable). Object `OrderReturn` (`api_order_return_present()`, the one `GET /orders/{id}/returns` answers). Code: `includes/api/resources/returns.php`.
- `modified_since` is not there: `order_returns` has no stamp that every writer moves (a refused refund puts `refunded_at` back to 0, a return label changes `carrier_code` with no stamp); see the plan file for the decision issue.

## What does POST /returns/{id} do?
- `action` approve, reject, ship, receive, close, refund: the buttons of `view_return.php` but the return label (it may cost money at the carrier). Writes only through `pg_return_staff_move()` and `pg_return_refund()`, so the decision and refund mails, the audit line and `order.return.approved|received|refunded` are the screen's.
- `api_return_action_check($row, $params, $context)` (pure) runs every check before `api_dry_run_stop()`: the action's starting status (`pg_return_staff_actions()`, refund only from received), a field the action does not read (422, not dropped), `status_was` (optional, 409 `return_changed`), the reject note, the refund's amount, method and `pg_return_refund_plan()` (driver present, more than 0 for a driver, at most `remaining`).
- approve without carrier and code takes `pg_return_suggested_approval()`; a carrier alone takes its latest code (`pg_return_approval_codes()`), as the form swaps an untouched code. ship with an empty carrier keeps the approval's. A manual refund keeps `reference`; a driver refund the service's id.
- The move passes the status it read as `status_was`, so `pg_return_move()` compares it again under the order's return lock (order-returns-stale-moves.md).

## Which answers?
- 409 `return_not_movable`, `return_changed`, `return_in_progress` (lock busy), `refund_driver_unavailable`, `refund_refused` (the service's words; the return stays received). An unclear driver answer is 200 with `refund.unclear` true, the return stays refunded, as on the screen.
- The write functions return `code` (`pg_return_staff_move()`: note_required, busy, changed; `pg_return_refund()`: not_installed, not_found, busy, not_received, no_driver, amount, amount_exceeds, changed, refused, unclear); `api_return_write_refuse()` maps the code, never the sentence.
- Option `log_user` on both: the activity log line names the application's owner (no signed-in person in the API); `pg_return_staff_move()` removes it before `pg_return_move()`.

## Tested?
- `tests/api_returns_test.php`: rows and scopes, field limits against the columns, the check's statuses, stale status, foreign field, note, approval suggestion, ship carrier, refund cases, write order (check, stop, write), the codes and the 409s described on the route. Not run against a database.
