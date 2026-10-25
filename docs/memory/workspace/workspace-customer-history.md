---
name: workspace-customer-history
description: A channel's customer history (CRM seams slice 4, #1681): union read of orders, invoices, quotes, forms and Workspace tags, rights per source, paging, API route, card part
---

## What is it?
- CRM seams slice 4 (plans/workspace-crm-seams.md, answer 3 a: union read, no activity table): one list, newest first, of what happened with a channel's customer. Code: includes/workspace/customer_history.php; tests: tests/workspace_customer_history_test.php.
- Sources: orders (`ws_customer_order_where()`, as the summary counts them), the invoices of the tied current accounts (sales and purchase, drafts and cancelled too), their sales quotes in every state (`erp_quote_state()`: open past its date is expired), forms the contact submitted, and messages in other channels and tasks that tag the customer or a tie (`ws_refs`).

## Who sees which source?
- `ws_customer_history_rights()`: orders the ecommerce right; invoices and sales quotes the ERP right (read-only included); forms the forms right, each only in a folder the reader may edit; messages and tasks any member, a message only in a channel they may read (`ws_can_read_channel()`), a task only where `ws_can_see_task()` says so. A source without its right is not read.
- The channel's own messages are left out: they are on screen already.

## How is a page read?
- Each source reads its newest `limit + 1` rows below `before` (forms three times as many, since closed folders drop out; `ws_refs` the newest 500 tags), by when it happened: `order_date`, `created_at` (invoices, quotes, messages, tasks), `submitted_timestamp`. Every invoice and quote insert writes `created_at`; the issue date is shown, not sorted by.
- `ws_customer_history_build()` (pure) lays the rows out as entries (kind, id, at, title, detail, status_label, amount in minor units or null, amount_text, url) and cuts at the limit (`ws_customer_history_limit()`: 20, 1-50).
- A page never ends inside a second: when the first entry left out shares its second with the last ones shown, those wait too and `before` is that second + 1. A page made of one second alone keeps it and `before` is that second.

## Where is it used?
- API: GET /workspace/channels/{id}/customer/history (`workspace.channels.customer_history`, workspace:read, an MCP tool), `before` and `limit`; read as the summary route reads (`ws_api_assistant_channel_reader()`, `ws_api_customer_viewer()` narrows ecommerce, erp, forms to orders:read, erp:read, forms:read). Objects `WorkspaceCustomerHistory`, `WorkspaceCustomerHistoryEntry`; `before` is null when `more` is false.
- Panel: action `ws_channel_customer_history` (adds `at_text`, `ws_time_label()`); the customer card's History button (assets/js/workspace_customer.js) reads the first page on demand, "Show older" the next.

## What was not checked live?
- No running site: the readers never ran against a database (2026-10-26). Deep paging stops finding tags older than the newest 500 `ws_refs` rows of the customer.

Related: docs/memory/workspace/workspace-customer-card.md, docs/memory/workspace/workspace-customer-summary.md
