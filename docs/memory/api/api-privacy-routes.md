---
name: api-privacy-routes
description: The read-only privacy API (KV6, #10897): GET /privacy/inventory, /privacy/requests and /privacy/requests/{id}, the privacy:read scope, views, cursor, steps
---

## What do the routes answer?
- (KV6, #10897, P12) `includes/api/resources/privacy.php`, read only. Writes (answer, erase, refuse) stay in the Privacy centre, where a person confirms them.
- `GET /privacy/inventory` (`privacy.inventory`, `PrivacyInventory`): `site_country`, `lines` (one per `pg_privacy_inventory()` table: keys of categories, subjects, ground and retention, always a list, plus `records` and `people` from `pg_privacy_inventory_counts()`, null when not known) and `transfers` (`pg_privacy_transfers_live()`: service id, country, data keys, personal, abroad, `state_unknown`). Not paged. Keys, not labels.
- `GET /privacy/requests` (`privacy.requests`, `PrivacyRequest`): the screen's views open (default), ending, answered, all through `pg_privacy_request_view_where()`, so only proven rows (`received_at > 0`). Newest first, cursor by id (`api_privacy_requests_sql()`), limit 1-250. No `message` or `answer` in the list; `days_left` only while open.
- `GET /privacy/requests/{id}` (`privacy.request`, `PrivacyRequestDetail`): the row through `pg_privacy_request_get()` (an unproven one is 404) with `message`, `answer` and `steps`: each audit line of `pg_privacy_request_history()` as action, at, actor and the kind, status and result of its `after_json`, nothing else of the line.

## Who may call them?
- Scope `privacy:read`, its own group Privacy in `api_scope_groups()` (no write scope), offered to roles 0-2 in `api_owner_scopes()`, as the Privacy centre is manager and up. No OAuth preset but All ticks it. Permission screen category Site.
- MCP tools by the route rule: `privacy_inventory`, `privacy_requests`, `privacy_request`. The long text of the first two sits in their `details` key (the 600-character tool budget).

## Tested?
- `tests/api_privacy_test.php`: the inventory presenter (null counts, retention as a list, transfers), the views' SQL and cursor, a request's fields and days left, the steps, the routes and the scope per role. Not run on a database or through MCP when written.

Related: [[privacy-requests]], [[privacy-inventory]], [[api-endpoint-rules]]
