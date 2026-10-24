---
name: workspace-quotes
description: Plan (2026-10-23, not started, answers 1, 3 given): Sales quote (ERP) and new Cart quote (site), never mixed: tags, proposals, templates, expiry, guest links
---

## What is asked? (owner, 2026-10-23)
- Frequent site and ERP quotes from the Workspace, never mixed; CRM-ready, more for MCP.

## What is there today?
- ERP: `erp_quotes` (`created_by`; includes/erp/quotes.php), MCP `erp_quotes_create`, bell `erp_alert_quote_expiry()`.
- Site: the saved-cart link (`orders.reference_code`), re-priced by `update_order_item_prices()`.
- tr.json: "Offer", "Sales quote": both "Teklif", "Quote" (`ws_message_quotes`) "Alıntı"; includes/fn/privacy_export.php's "Price quote" should read "Sales quote".

## What is proposed? (not approved yet)
- Sales quote (`erp_quote`, ERP right), Cart quote (`cart_quote`, ecommerce right): never a bare "quote", no conversion, no payment link on sales quotes (erp/erp-not-built-on-purpose.md).
- Candidate: Pinegrap AI drafts a sales or cart quote from channel messages, after slices 3, 5.

## Slices
1. No schema: `erp_quote` tag (refs.php): `ws_record_type_keys()`, `ws_ref_types()` (`lang('Sales quote')`, prefixes `satis`, `salesquote`; Offer keeps `teklif`), `ws_refs_resolve()` (number, account, total, `erp_quote_state()`), `ws_ref_search()`; `erp:read` in `ws_api_search_record_scopes()`, `ws_ai_type_scope()`; "Copy" (add_erp_quote.php `?copy=`) in `chipMenus()`.
2. No schema: "New sales quote" (add_erp_quote.php `?account_id=`) on a channel whose customer (`ws_channel_customer_of()`) is or links to (`ws_customer_link_tokens()`) an ERP account; never `erp_account_for_contact()` (it creates).
3. No schema, after the MCP plan's slice 3: `erp_quote` change kind in `ws_change_types()`, a structured line kind (`lines` is text), writer `erp_quote_save()`, proposed via `workspace.changes.propose`; direct AI writes per the MCP plan's question.
4. Schema, ask first (next free ERP label): `erp_quote_templates` (proposed: name, scope, owner, `form_data`); "From template" on add_erp_quote.php, ERP API routes (`dry_run`). Tell the ERP owner (local work: plans/erp-edocument-leftovers.md).
5. Schema, ask first (next free general label): `cart_quotes`, `cart_quote_items` (proposed: token, contact, `valid_until`, status, order id) in `get_tables()`; a PR each: panel, channel form; a root file adding lines by `add_order_item()` (stock, min/max, `pg_order_item_active_sql()`), get_express_order.php, get_order_preview.php, get_shopping_cart.php totals unchanged; tag (`sepet`, `cartquote`); routes (`dry_run`, shared `*_check()`, `cart_quote.*` events); later the cart of plans/storefront-api-agentic-commerce.md. Link: GET changes nothing (previews, mail scanners): confirm page, POST with form token; token hashed with a hint (`ws_approval_guests`); WAF rate limit; no contact data to any holder. `cart_quotes.contact_id` reaches `pg_privacy_export_find()`, erasure, contact merge.
6. No schema: this plan owns the expiry metric (`erp_quote_expiring_count()`) beside the bell; an optional sales-quote-tagged task on its channel or for `created_by` via `ws_followup_task_open()` (proposed, CRM seams slice 5). Invoicing from the card: only an `erp_quote` change action.
7. Ask first (link token, maybe schema): a guest accepts a sales quote by link with the signature evidence (`erp_signatures`, `erp_quote_status_refusal()`; erp/erp-quotes.md), never a bare status flip.

## Which are answered? (owner, 2026-10-24: 1 a, 3 b)
1. Names: "Satış teklifi" / "Sepet teklifi"; Offer stays "Teklif" (not "Kampanya", no shared tag).
Open: 2. Template scope: personal, channel, site-wide → personal, site-wide.
3. Cart quote: own tables at catalogue prices, valid until expired or ordered; a single-use offer code later; never a per-line price (not the saved-cart link).

## Where to start?
- Slice 1 after plans/workspace-crm-seams.md slice 1 (record-kind drift test); extend `test_ws_api_search_record_scopes_name_known_kinds`.
