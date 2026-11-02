---
name: external-api-coverage
description: External API (integration.php): find what the panel can do that the API cannot, then add the missing endpoints in steps (owner, 2026-11-01); claim as [plan:external-api-coverage] X<n>
---

## What did the owner ask?
- Many things the panel manages have no endpoint in `integration.php`; they are to be added. Not the storefront API (visitor carts and checkout: `storefront-api-*.md`) nor the vendor client (`kodpen-vendor-api-client*.md`).

## Which steps are there?
- X0 (S, docs only) Gap list. Ready to start.
  - Compare the panel's areas (menu slots, `pg_panel_actions()`, list and edit screens of every module) with the endpoints of `api_schema()`, `includes/api/resources/*.php` and the modules' `includes/<module>/api.php`. Today's resources: account, audit_log, calendars, campaigns, cart_quotes, comments, commissions, contact_groups, customers, design, discovery, files, folders, forms, gift_cards, inventory, key_codes, menus, meta, offers, orders, pages, product_attributes, product_groups, products, reports, seo, short_links, site_log, store, system, visitors, webhooks.
  - Write the gaps into this file as steps X1… of about 1,000 product lines each, most useful first (what an outside system needs to sync: shipping, taxes, returns, subscriptions, users and roles, redirects first), each naming its resource, verbs, scopes and webhook events.
  - A gap that needs a schema change or decides product behaviour is listed with a decision question (Working mode 10), not as a step.
- X1… are added by X0; each is `free` once written.

## What is settled?
- Every endpoint follows the API key rules (`api/README.md`): one row in the schema (router, validator, OpenAPI, MCP and scope screen read it), a scope in `api_scope_groups()` and its owner ceiling, the user role's row rule, `api_request_input()`, `api_dry_run_stop()` before the first write, POST twins for PATCH and DELETE, `max_length` within the column, webhook events through `pg_announce()`.
- An endpoint reuses the panel's own write path (the same function the screen calls), so the rules of the screen hold for the API.
- Tests: the schema test families already cover a new row (`php tools/check_api_schema.php`); each step adds tests for its handlers.

## Where to start?
- `docs/memory/api/README.md` and `api-endpoint-rules.md`; `pinegrap/includes/api/schema.php`, `includes/api/resources/`.
