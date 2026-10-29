---
name: workspace-quotes
description: Plan (2026-10-23, 1 #1546, 2 #1614, 3 #1705, 6 #1826 #1907 done, answers 1, 3 given): Sales quote (ERP) and new Cart quote (site), never mixed: tags, proposals, templates, expiry, guest links
---

## What is asked? (owner, 2026-10-23)
- Frequent site and ERP quotes from the Workspace, never mixed; CRM-ready, more for MCP.

## What is there today?
- ERP: `erp_quotes` (`created_by`; includes/erp/quotes.php), MCP `erp_quotes_create`, bell `erp_alert_quote_expiry()`.
- Site: the saved-cart link (`orders.reference_code`), re-priced by `update_order_item_prices()`.
- tr.json: "Offer" "Teklif", "Sales quote" "Satış teklifi" (privacy export too), "Quote" (`ws_message_quotes`) "Alıntı".

## What is proposed? (not approved yet)
- Sales quote (`erp_quote`, ERP right), Cart quote (`cart_quote`, ecommerce right): never a bare "quote", no conversion, no payment link on sales quotes (erp/erp-not-built-on-purpose.md).
- Candidate: Pinegrap AI drafts a sales or cart quote from channel messages, after slices 3, 5.

## Slices
1. PR #1546: the `erp_quote` tag (workspace/workspace-record-kinds.md, erp/erp-quotes.md).
2. PR #1614 (no schema): "New sales quote" in the channel menu opens add_erp_quote.php `?account_id=` on the account the customer is or links to; none found, no item; never `erp_account_for_contact()` (workspace/workspace-new-sales-quote.md).
3. PR #1705 (no schema): `erp_quote` change kind, create only, line kind `erp_lines`, writer `erp_quote_save()`, proposed via `workspace.changes.propose`, in `ws_api_changes_param()` and Pinegrap AI's guide (workspace/workspace-sales-quote-change.md). Not built: changing an open quote from a card.
4. Schema, ask first (next free ERP label): `erp_quote_templates` (proposed: name, scope, owner, `form_data`); "From template" on add_erp_quote.php, ERP API routes (`dry_run`). Tell the ERP owner (local work: plans/erp-edocument-leftovers.md).
5. Schema, ask first (next free general label): `cart_quotes`, `cart_quote_items` (proposed: token, contact, `valid_until`, status, order id) in `get_tables()`; a PR each: panel, channel form; a root file adding lines by `add_order_item()` (stock, min/max, `pg_order_item_active_sql()`), get_express_order.php, get_order_preview.php, get_shopping_cart.php totals unchanged; tag (`sepet`, `cartquote`); routes (`dry_run`, shared `*_check()`, `cart_quote.*` events); later the cart of plans/storefront-api-agentic-commerce.md. Link: GET changes nothing (previews, mail scanners): confirm page, POST with form token; token hashed with a hint (`ws_approval_guests`); WAF rate limit; no contact data to any holder. `cart_quotes.contact_id` reaches `pg_privacy_export_find()`, erasure, contact merge.
6. PR #1826 (no schema): the bell's quote opens a follow-up when `ERP_QUOTE_EXPIRY_FOLLOWUP` is on (erp/erp-alerts.md). PR #1907: the `erp_quote` change action `invoice` (erp_quote_to_invoice(), workspace/workspace-sales-quote-change.md). Not built: a Settings checkbox (needs a `config` column, asked).
7. Ask first (link token, maybe schema): a guest accepts a sales quote by link with the signature evidence (`erp_signatures`, `erp_quote_status_refusal()`; erp/erp-quotes.md), never a bare status flip.

## Which are answered? (owner, 2026-10-24: 1 a, 3 b)
1. Names: "Satış teklifi" / "Sepet teklifi"; Offer stays "Teklif" (not "Kampanya", no shared tag).
Open: 2. Template scope: personal, channel, site-wide → personal, site-wide.
3. Cart quote: own tables at catalogue prices, valid until expired or ordered; a single-use offer code later; never a per-line price (not the saved-cart link).

## Where to start?
- Slices 1-3 and 6 are built (6's Settings checkbox waits for the owner). 4 and 7 ask the owner first.
- 5a (tables 11.0.124, model, link page `cart_quote.php`, order completion, contact holder, export, erasure; commerce/cart-quotes.md) is in its PR. Next 5b: panel screen (list, make, cancel) and the Workspace card (`ws_customer_cart_quote_rows()`); then channel form, tag, `cart_quote.*` routes.
