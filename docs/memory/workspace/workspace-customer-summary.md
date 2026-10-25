---
name: workspace-customer-summary
description: A channel's customer summary (GET /workspace/channels/{id}/customer): identity, orders, ERP balance, sales quotes, forms; rights and key scopes per section, money, the cart quote seam
---

## What is it?
- CRM seams slice 2 (plans/workspace-crm-seams.md): `ws_customer_identity($viewer, $channel)` and `ws_customer_summary($viewer, $channel, $identity = null)` at the end of includes/workspace/customer.php, for the panel (slice 3) and the API alike. Read-only: no write, no action offered. Tests: tests/workspace_customer_summary_test.php.

## Who is the customer?
- The record `ws_channel_customer_of()` names, resolved for the reader by `ws_refs_resolve()`, with the ties of `ws_customer_link_tokens()` the reader may see. A record the reader may not see goes by kind and number ("Contact #12"), no detail, no url. A deleted customer is null.
- `contact_id`, `user_ids`, `erp_account_ids` (every tie, seen or not) are what the sections read by; never shown. Unlike `ws_channel_customer_present()`, nothing is written.

## What do the sections read, and who sees them?
- `ws_customer_summary_rights()`: orders and cart quotes the ecommerce right; `erp_accounts` (balance) and `sales_quotes` the ERP right, the read-only accountant included; forms the forms right, each form only in a folder the reader may edit (`ws_viewer_folder_open()`, shared with the form tag in refs.php).
- A section without its right is not even queried; an empty one is left out (null in the API).
- Orders: `status <> 'incomplete'` by `contact_id`, the tied users' `user_id`, and `erp_account_id` once that column exists; count plus latest 5.
- Balance: `erp_accounts.balance` (the ledger's cache, base currency, positive when the account owes), active accounts first.
- Sales quotes: `erp_quote_rows(['state' => 'open', 'account_id' => …])` and `erp_quote_open_count()` per tied account, newest 5.
- Forms: `forms.contact_id` of the customer's contact, latest 5 the reader may open (15 read). forms has no index on `contact_id`: the newest-first scan stops early only for customers with recent forms.

## How is money written?
- Integer minor units throughout (`ws_customer_minor()` rounds, never cuts). Text by `ws_customer_money_text()`: the site's currency as the order screens write it (`prepare_amount()`, no visitor conversion, symbol decoded), a sales quote's own currency by `erp_money_out_currency()`.

## Where are cart quotes?
- A seam: `ws_customer_cart_quote_rows()` reads nothing and the builder lays out no `cart_quotes` until plans/workspace-quotes.md slice 5 adds the tables; then they come as their own section under the ecommerce right, never among sales quotes. Not in the API yet.

## How does the API answer?
- GET /workspace/channels/{id}/customer (`workspace.channels.customer`, row in includes/workspace/api.php, code in api_customer.php): `workspace:read`, an MCP tool like the context (the MCP resource comes with plans/workspace-mcp-capabilities.md).
- The channel is read by `ws_api_assistant_channel_reader()` (api_claude.php), shared with GET …/context: 404 as every channel route, Claude's key only where Claude may be asked, or through the person who asked where its owner is not in.
- `ws_api_customer_viewer()` narrows the reader to the key's scopes first (`ws_api_customer_scopes()`: orders `orders:read`, contacts `customers:read`, ERP `erp:read`, forms `forms:read`, as `ws_api_search_record_scopes()`); a scope never opens what the person may not see.
- Objects `WorkspaceChannelCustomer`, `WorkspaceCustomerRecord`, `…Orders`, `…Order`, `…Account`, `…Quotes`, `…Quote`, `…Form`; their presenter pairs are in tools/check_api_schema.php.

## What was not checked live?
- No running site: queries and the route were not run against a database (2026-10-25).

Related: docs/memory/workspace/workspace-module-decisions.md, docs/memory/workspace/workspace-record-kinds.md, docs/memory/erp/erp-quotes.md
