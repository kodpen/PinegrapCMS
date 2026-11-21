---
name: api-privacy-routes
description: The read-only privacy API (KV6, #10897): GET /privacy/inventory, /privacy/requests and /privacy/requests/{id}, the privacy:read scope, views, cursor, steps
---

## What do the routes answer?
- (KV6, #10897, P12) `includes/api/resources/privacy.php`, read only. Writes (answer, erase, refuse) stay in the Privacy centre, where a person confirms them.
- `GET /privacy/inventory` (`PrivacyInventory`): `site_country`, `lines` (one per `pg_privacy_inventory()` table: categories, subjects, ground and retention each as `{key, label}` with the English label, `records` and `people` from `pg_privacy_inventory_counts()`, null when not known) and `transfers` (`pg_privacy_transfers_live()`: service, country, data as `{key, label}`, personal, abroad, `state_unknown`). Not paged.
- `GET /privacy/requests` (`PrivacyRequest`): views open (default), ending, answered, all through `pg_privacy_request_view_where()`, so proven rows only. Newest first by id with the cursor, limit 1-250. No `message` or `answer`; `days_left` only while open.
- `GET /privacy/requests/{id}` (`PrivacyRequestDetail`): `pg_privacy_request_get()` (unproven is 404) plus `message`, `answer` and `steps`: action, at, `user_id`, `user_name` and the kind, status and result of each audit line's `after_json`.
- Both request routes answer 503 `service_unavailable` before the `privacy_requests` table exists, never an empty list.

## What never goes out?
- Architect (2026-11-21, agent channel message 11219): none of the three routes carries the proof link or its hash, the prepared export file or a link to it, or an IP; a step names its actor by user id and name only.

## Who may call them?
- Architect (2026-11-21, agent channel message 11219): a new read-only group Privacy, `privacy:read`, in `api_scope_groups()`, ceiling roles 0-2 in `api_owner_scopes()`. Not `customers:read`: a role-3 owner with the contacts right must not read requests (403 `insufficient_scope`).
- Off in the five OAuth presets, on in All; in `api_assistant_scopes_left_out()`, so neither an assistant's key nor the plugin reads hold it. Permission screen category Site.
- MCP tools by the route rule: `privacy_inventory`, `privacy_requests`, `privacy_request`, each saying read only. Long text sits in the routes' `details` key (the 600-character tool budget).

## Why do the steps not use pg_privacy_request_history()?
- It selects `actor_name`, which `audit_log` lacks (its columns are `user_id`, `username`), so it reads nothing; the API reads its own `api_privacy_request_steps_sql()`.

## Tested?
- `tests/api_privacy_test.php`: labels, null counts, the views' SQL and cursor, the list without text, the steps without address, the 503s, the role-3 403, the assistant lists. Not run on a database or through MCP when written.

Related: [[privacy-requests]], [[privacy-inventory]], [[api-endpoint-rules]]
