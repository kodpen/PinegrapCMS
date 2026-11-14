---
name: api-plugin-scopes
description: The 'moves_money' route mark and api_plugin_scopes(), the scopes an assistant plugin is meant to hold: marked routes, offered writes, and the test that keeps them apart
---

## What does `'moves_money' => true` mean?
- A route row that moves money or makes a cost: a refund, a carrier label, a counter sale with its payment, a gift card or store credit, a till/bank/cheque movement, a commission marked paid, a subscription charged again.
- Nothing reads it at run time. `tests/api_plugin_scopes_test.php` reads it; a new route of that kind gets the mark (comment at the top of `includes/api/schema.php`).
- Marked: `returns.update`, `orders.labels.create`, `orders.create`, `gift_cards.create/update/delete`, `customers.credit.issue/void`, `customers.subscriptions.resume`, `commissions.update`, `erp.cash_transfers.create`, `erp.cheques.create/step`, `erp.expenses.pay`, `erp.receipts.create/cancel`. The test pins this list, so a mark cannot be dropped in silence.

## What is `api_plugin_scopes()`?
- In `includes/api/scopes.php`: every group's read except `api_assistant_scopes_left_out()`, plus the writes of `api_plugin_writes()` (bookings, calendars, campaigns, comments, contact groups, erp, files, forms, inventory, offers, pages, products, store, tasks, translations, workspace).
- Never in it: `orders:write`, `gift_cards:write`, `erp_cash:write`, `commissions:write`, `customers:write`, `webhooks:manage`, `account:write`, `users:write`.
- The test fails when a marked route sits under a scope of the set; it builds the scope groups from the routes, so module scopes count without a database.

## What does it not do?
- It is a definition: the consent screen, its presets and the token are unchanged; the person still ticks every write by hand (`api-oauth-consent-presets.md`). A consent-screen opt-in for money routes is not built (architect, 2026-11-14, msg 2493).
- The plugin's skills and README name this set (CB3).

## What else does the test guard?
- Every offered tool name fits 64 characters (Claude's directory policy) and no two routes become one tool name, since `pg_mcp_routes_for()` keeps only the first.

Related: docs/memory/api/claude-plugin-tool-check.md, docs/memory/api/api-mcp-server.md
