---
name: workspace-customer-summary
description: A channel's customer summary (GET /workspace/channels/{id}/customer): identity, orders, ERP balance, sales quotes, forms; rights and key scopes per section, money, the cart quote seam
---

## What is it?
- CRM seams slice 2 (plans/workspace-crm-seams.md): `ws_customer_identity()` and `ws_customer_summary()` in includes/workspace/customer.php, for the panel (slice 3) and the API. Tests: tests/workspace_customer_summary_test.php.

## Who is the customer?
- The record `ws_channel_customer_of()` names, resolved for the reader by `ws_refs_resolve()`, with the ties of `ws_customer_link_tokens()` the reader may see. A record the reader may not see goes by kind and number ("Contact #12"), no detail, no url.
- Role 3: a contact outside the reader's contact groups too (`ws_viewer_contacts_open()`, the rule of `validate_contact_access()`, in `ws_refs_resolve()` (tags, channel card), #1657; `ws_customer_refs_narrow()`).
- A deleted customer (or an erp_account, ERP off) is null: `ws_refs_resolve()` marks a gone record `gone`, apart from known false. `ws_customer_present()` still draws "Deleted record".
- `contact_id`, `user_ids`, `erp_account_ids` (every tie, seen or not) are what the sections read by; never shown. Nothing is written.

## What do the sections read, and who sees them?
- `ws_customer_summary_rights()`: orders and cart quotes the ecommerce right; `erp_accounts` (balance) and `sales_quotes` the ERP right, read-only included; forms the forms right, each form only in a folder the reader may edit (`ws_viewer_folder_open()`, as the form tag).
- A section without its right is not even queried; an empty one is left out (null in the API).
- Orders (`ws_customer_order_where()`): `status <> 'incomplete'` by `contact_id`, the tied users' `user_id` but no `type = 'local'` (a counter sale's `user_id` is the cashier, not the buyer), and `erp_account_id` once there; count plus latest 5.
- Balance: `erp_accounts.balance` (ledger cache, base currency, positive when the account owes), active accounts first.
- Sales quotes: `erp_quote_rows()` (state open) and `erp_quote_open_count()` per tied account, newest 5.
- Forms: `forms.contact_id`, latest 5 the reader may open (15 read).
- No `contact_id` index on orders or forms: both order reads scan orders, carts too (a UNION per tie gains nothing); forms stop early only for recent ones. An index is a schema step: ask first.

## How is money written?
- Integer minor units throughout (`ws_customer_minor()` rounds, never cuts). Text by `ws_customer_money_text()`: the site's currency as the order screens write it (`prepare_amount()`, no visitor conversion, symbol decoded), a sales quote's own currency by `erp_money_out_currency()`.

## Where are cart quotes?
- A seam: `ws_customer_cart_quote_rows()` reads nothing until plans/workspace-quotes.md slice 5 adds the tables; then their own section under the ecommerce right, never among sales quotes. Not in the API yet.

## How does the API answer?
- GET /workspace/channels/{id}/customer (`workspace.channels.customer`, row in includes/workspace/api.php, code api_customer.php): `workspace:read`, an MCP tool like the context (resource: plans/workspace-mcp-capabilities.md).
- The channel: `ws_api_assistant_channel_reader()` (api_claude.php), shared with GET …/context: 404 as every channel route; Claude's key, where Claude may be asked, reads as the asker when its owner is not in.
- `ws_api_customer_viewer()` narrows the reader to the key's scopes first (`ws_api_customer_scopes()`: `orders:read`, `customers:read`, `erp:read`, `forms:read`, as search); no scope reads users (as search), so a user goes by kind and number. A scope never opens what the person may not see.
- Objects `WorkspaceChannelCustomer`, `WorkspaceCustomer*`; presenter pairs in tools/check_api_schema.php.

## What was not checked live?
- The route and its queries never ran on a site (2026-10-25).

Related: docs/memory/workspace/workspace-module-decisions.md, docs/memory/workspace/workspace-record-kinds.md, docs/memory/erp/erp-quotes.md
