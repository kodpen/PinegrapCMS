---
name: api-list-newest-device-lists
description: Which cursor-paged lists a team device reads take sort=newest (M8a), which are newest first already, and which cannot take it
---

## Which lists did M8a give sort=newest? (PR #M8A)
- The lists in the team devices ceiling (`api-team-devices-switch.md`) that walked oldest first: `GET /bookings` (start time, then id), `GET /calendars/events/{id}/tickets` (id), `GET /commissions` and `GET /commissions/recurring-profiles` (last change, then id).
- Also `GET /files` (upload time, then id), `GET /inventory` (product id), `GET /marketplaces/{account}/repricing` (product id), `GET /product-attributes` (id), `GET /product-groups` (last change, then id), `GET /products/{id}/price-history` (change time, then id).
- Each declares the `sort` row with `oldest` the default and walks through `list_order.php` (`api-list-newest-first.md`); the default walk and its cursors are unchanged. `tests/api_list_order_test.php` names each handler.

## Which device lists are newest first already, with no sort row?
- `GET /comments` and `GET /products/{id}/reviews`, `GET /cart-quotes`, `GET /customers/{id}/consents`, `/subscriptions` and `/wishlist`, `GET /inventory/moves`, `GET /notifications`, `GET /shipments/problems`, `GET /store/verified-addresses`, `GET /returns`; `GET /orders` has `sort=placed_desc`.
- Workspace: `GET /workspace/inbox`, `/changes`, `/decisions`, `/assistant-requests`, `/time`, `/flows/{id}/runs` and `/flows/{id}/versions`.
- An oldest-first walk for these would be a new default, not a second sort: none was asked for.

## Which cannot take it?
- `GET /reports/customer-rhythm`: ranked by expected value, not by a time or an id, so newest has no meaning there.
- `GET /workspace/sync`: its cursor is a set of positions in several feeds, read forward only.
- `GET /workspace/tasks`: no cursor paging at all; its order is the task list's own. Still open, as `api-list-newest-first.md` says.
