---
name: api-lead-journey
description: The visitor journey's API (LJ6): GET /customers/hot (the MCP read tool, followed_by), /customers/{id}/journey, /customers/{id}/score, /reports/lead-sources, interest_score and topics on Customer
---

## Which routes are there?
- `includes/api/resources/leads.php`, all GET, all MCP tools by the route rule (`customers_hot` is the hot contacts read tool):
- `customers.hot` `/customers/hot` (customers:read): the Hot contacts view, highest score first, then id; cursor carries the score and the id (`api_lead_hot_page_sql()`). Fields as `customer.hot` (`pg_lead_event_payload()`) plus name, company, e-mail, `hot_at`, `followed_by`. `threshold` beside `data`.
- `customers.journey` `/customers/{id}/journey` (customers:read): `pg_lead_journey_read()`'s rows a page at a time (cursor = offset), `first_touch` beside `data`. Forms only with forms:read (user role: folders it may edit), orders only with orders:read on a shop. No panel addresses.
- `customers.score` `/customers/{id}/score` (customers:read): the contact_scores row, `scored` false and scores null without one; reasons of both scores with `score` interest or fit; topics with kind, weight.
- `reports.lead_sources` `/reports/lead-sources` (visitors:read, the panel's `validate_visitors_access()`): from/to days (`api_visitors_window()`, 400 days), touch, by; revenue and currency only on a shop to a key with orders:read.

## What is followed_by?
- The people of the "Followed by" column (`visitors/lead-hot-contacts-followed-by.md`): Workspace user ids from `pg_contact_list_followers()`, one query for the page; `[]` when nobody follows; null with the Workspace off (`pg_contact_list_workspace_on()`).

## And the Customer answer?
- `interest_score` (null before a score) and `topics` (interest names, strongest first) on every Customer read: `api_customer_select()` LEFT JOINs contact_scores through `api_customer_score_sql()`, NULLs before the table exists.

## Who reaches which rows?
- The customers rules: role 3 only contacts of its contact groups (hot by `api_customer_owner_filter()`, the others by `api_customer_reachable_or_fail()`, 404 outside); `tests/api_customers_test.php` counts the routes.
- A contact with panel work has no journey and no score (J14).

## Tests?
- `tests/api_leads_test.php`: routes, presenters against their schemas, followed_by null / empty / ids, the hot keyset, revenue hidden, Customer fields.

## What was not checked live?
- No running site: the queries never ran against a database (2026-11-24).
