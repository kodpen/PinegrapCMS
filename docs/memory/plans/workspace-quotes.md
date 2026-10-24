---
name: workspace-quotes
description: Plan (2026-10-23, not started, answers open): Workspace quotes in two kinds never mixed: ERP sales quotes (built) and a new site cart quote; tags, prefill, templates, expiry
---

## What is asked? (owner, 2026-10-23)
- Frequent quotes from the Workspace, one kind for the site, one for the ERP, never mixed; part of the CRM-ready, more-for-MCP goal.

## What is there today?
- ERP: `erp_quotes` (4.92, with `currency`, `created_by`), includes/erp/quotes.php (`erp_quote_save()`, `erp_quote_state()`, `erp_quote_to_invoice()`), add_erp_quote.php `?copy=`, `?account_id=`, `/erp/quotes` (MCP `erp_quotes_create`), bell `erp_alert_quote_expiry()`.
- Workspace: no quote in `ws_record_type_keys()` or `ws_change_types()`; ERP documents are tag-only. A channel customer (`ws_channel_customer_of()`) may be an `erp_account`; `ws_customer_link_tokens()` finds a contact's accounts read-only.
- Site: no quote. The saved-cart link (`orders.reference_code`, `_eo_restore_cart_from_reference_code()`) hands the cart over, `update_order_item_prices()` re-prices it, job.php mails it as abandoned.
- tr.json: "Offer" and "Sales quote" are both "Teklif", "Price quote" already names ERP quotes (includes/fn/privacy_export.php), "Quote" is "Alıntı"; `teklif` is an offer prefix in `ws_ref_types()`.

## What is proposed? (not approved yet)
- Two kinds, own name, tag, table, right: ERP quote (`erp_quote`, ERP right), cart quote (new `cart_quote`, ecommerce right). No conversion; no payment link on an ERP quote, as invoices have none (erp/erp-not-built-on-purpose.md).
- A cart quote holds products and quantities; its link adds them to the visitor's own cart (`add_order_item()`), priced as any cart. Checkout, express order, preview totals unchanged; legacy get_shopping_cart.php is not touched.

## Slices
1. No schema: `erp_quote` tag (refs.php): `ws_record_type_keys()`, `ws_ref_types()` (`lang('Sales quote')`, prefix `quote`), `ws_refs_resolve()` (number, account, `erp_money_out_currency()`, `erp_quote_state()`), `ws_ref_search()`; `erp:read` in `ws_api_search_record_scopes()` and `ws_ai_type_scope()`; "Copy" in `chipMenus()` (assets/js/workspace.js).
2. No schema: "New quote" on a channel whose customer is or links to an ERP account: add_erp_quote.php `?account_id=`; never `erp_account_for_contact()` (it creates).
3. No schema: `erp_quote` create kind in `ws_change_types()`, a new structured line kind (`lines` is text), writer `erp_quote_save()`; assistants propose, the person applies.
4. Schema, ask first (next free ERP label): new `erp_quote_templates` (name, scope, owner, `form_data`); "From template" on add_erp_quote.php, routes in includes/erp/api.php with `dry_run`.
5. Schema, ask first (next free general label): new `cart_quotes`, `cart_quote_items` (token, contact, `valid_until`, status, order id) in `get_tables()`; then a PR each: panel and channel form, a new root file filling the cart, tag, routes (`dry_run`, shared `*_check()`, `cart_quote.*` events). Later via `/storefront/cart` (plans/storefront-api-agentic-commerce.md).
6. No schema: expiry follow-up, an optional task tagged with the quote, on its channel or for `created_by` (`ws_inbox` has no record column); shared opener with item 4 of plans/workspace-phase3-leftovers.md. Invoicing from the card only as an `erp_quote` change action.

## Open questions (recommendation)
1. Names: ERP "Sales quote" / "Satış teklifi", site "Cart quote" / "Sepet teklifi", prefixes `satis`, `sepet` → yes.
2. Template scope: personal, channel, site-wide → personal, site-wide.
3. Cart quote base: (a) an incomplete order, (b) own tables → b.
4. Special price: (a) none, (b) single-use offer code, (c) per-line price (checkout) → a (account prices exist: erp/erp-account-prices.md), b later.
5. Link: any holder, or the contact signed in → any holder, until it expires or is ordered.

## Where to start?
- Slice 1; test: each record kind but `user_account` has both scopes.
