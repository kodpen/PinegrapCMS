---
name: workspace-quotes
description: Plan (2026-10-23; every slice built, closes in a memory-only PR): Sales quote (ERP) and new Cart quote (site), never mixed: tags, proposals, templates, expiry, guest links
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
4. Panel part done (#3717; erp/erp-quote-templates.md): `erp_quote_templates`, "From template" on add_erp_quote.php. API part done (#3921): `/erp/quote-templates` routes (`dry_run`), `erp.quote_template.*` events.
5. Schema approved (#3072, `workflow/decisions-made-schema-3.md`): `cart_quotes`, `cart_quote_items`. Built in 5a-5e (below; commerce/cart-quotes.md): tables, link page, panel, customer card, tag, API routes and events. Later: the cart of plans/storefront-api-agentic-commerce.md.
6. PR #1826 (no schema): the bell's quote opens a follow-up when `ERP_QUOTE_EXPIRY_FOLLOWUP` is on (erp/erp-alerts.md). PR #1907: the `erp_quote` change action `invoice` (erp_quote_to_invoice(), workspace/workspace-sales-quote-change.md). Settings checkbox done (#3717).
7. Done (#3761; erp/erp-quote-guest-link.md), approved in #3671 (schema-6, `decisions-made-product-9.md`): a guest accepts a sales quote by link (`erp_quote_links`, `erp_quote.php`) with the signature evidence (`erp_signatures`, `erp_quote_status_refusal()`; erp/erp-quotes.md), never a bare status flip; the link expires with `valid_until`, a new link revokes the old.

## Which are answered? (owner, 2026-10-24: 1 a, 3 b)
1. Names: "Satış teklifi" / "Sepet teklifi"; Offer stays "Teklif" (not "Kampanya", no shared tag).
2. Template scope: personal or site-wide (#3672, built #3717).
3. Cart quote: own tables at catalogue prices, valid until expired or ordered; a single-use offer code later; never a per-line price (not the saved-cart link).

## Where to start?
- Nothing approved remains: slices 1-7 are built (5a-5e and 7 in commerce/cart-quotes.md, erp/erp-quote-guest-link.md; 4 in erp/erp-quote-templates.md).
- The plan closes in a memory-only PR after the CRM10.3 PR, which edits plans/workspace-growth-2026-10.md and plans/workspace-crm-seams.md that link here; move lasting decisions to their topic files then.
