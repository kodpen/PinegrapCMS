---
name: workspace-customer-card
description: The customer card on a Workspace channel's Summary tab (CRM seams slice 3, #1666): the API's customer summary through the ws_channel_customer panel action, addresses per row, ids kept back
---

## What is it?
- CRM seams slice 3 (plans/workspace-crm-seams.md): on the Summary tab of a channel about a customer (`channel.customer`), a card above the time card: the customer (name, kind, detail, link), the ties the reader sees, then Orders (count, latest 5), Balance (current accounts), Open sales quotes (count, latest 5), Form submissions. Nothing to show: "Nothing to show for this customer yet."
- Under the sections a History button reads the customer's history on demand (workspace-customer-history.md).
- Code: `ws_customer_card()` and `ws_customer_card_build()` in includes/workspace/customer.php; assets/js/workspace_customer.js (`PGWsCustomer.card()`, in the workspace.min.js bundle before workspace.js); `drawSummary()` in workspace.js asks for it. Tests: tests/workspace_customer_card_test.php.

## Where does the data come from?
- Panel action `ws_channel_customer` (row in `pg_panel_actions()`, case in includes/workspace/actions.php): `ws_action_channel()` (not found for a channel the reader may not read), then `ws_customer_card()`: `ws_customer_identity()` and `ws_customer_summary()` for that reader, the same reads as GET /workspace/channels/{id}/customer (workspace-customer-summary.md). So the rights per section, the contact-group rule and the ERP read-only right hold as there.
- `card` is null for a channel about nobody or a customer gone since; the screen then removes the card. A failed request removes it too and shows the error.

## What does the build add, and what does it leave out?
- Per row the screen its tag links to: `view_order.php`, `edit_erp_account.php`, `edit_erp_quote.php`, `edit_submitted_form.php`. A section is only there when the reader holds its right; a form only when its folder is open (summary rule), so no row carries an address the tag would not.
- Days as `d.m.Y` (`ws_customer_date_text()`: a moment in the site's zone, a Y-m-d column taken as it is); "Valid until …" for a quote; `status_label` Passive (account) or Incomplete (form); a quote without a number goes by `#id`.
- Left behind: `contact_id`, `user_ids`, `erp_account_ids` of the identity. Amounts and their texts pass through unchanged (integer minor units).

## What was not checked live?
- No running site: the action and the card never ran against a database or in a browser (2026-10-25). The layout uses Bootstrap utility classes only, no CSS of its own.

Related: docs/memory/workspace/workspace-customer-summary.md, docs/memory/plans/workspace-crm-seams.md
