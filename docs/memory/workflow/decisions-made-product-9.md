---
name: decisions-made-product-9
description: Decisions list, product part 9 (2026-10-30 afternoon on): designed form rules for visitors only, marketplace orders invoiced by the ERP behind a switch, sales quote guest links and templates
---

## Where are the rules and the earlier parts?
- `decisions-made.md` (rules) and the product parts `-2` to `-8`. Reasons are on the issues.

## Do designed forms refuse a past date and require a field for an answer? (#3669)
- Decided (architect, 2026-10-30): yes, through `form_fields.rules` (`decisions-made-schema-6.md`): "not before today" on a date control and "required when another field has one of the given answers". Visitor submissions only (`custom_form.php`, the browser through `pg_cf_validation_script()`); staff edits in the panel, the API and MCP are exempt, so yesterday's booking can still be closed. Cafe and Clinic set the rules at install; an installed site sets them in the designer rows; no content migration.

## Are marketplace orders invoiced by the ERP automatically? (#3670)
- Decided (architect, 2026-10-30): only when the ERP card's new "Marketplace orders too" box is on (`config.erp_auto_invoice_marketplace`, default off: a site that invoices them elsewhere must not get double invoices on update). The card's trigger applies: `order_paid` at import, `order_shipped` when the marketplace's tracking arrives (`mp_order_apply_tracking()` calls `pg_erp_auto_invoice()`) or the operator ships; both filters apply; the invoice link to the marketplace stays typed by hand. A public signed invoice-PDF address with an automatic link and Invoiced push is a later step with its own design and architect question.

## May a customer without an account accept a sales quote by link? (#3671)
- Decided (architect, 2026-10-30, plan `workspace-quotes.md` step 7): yes, in 2026.11.0: `erp_quote.php` at the root shows the quote read-only with accept/sign, no payment; the signature evidence goes to `erp_signatures` and the status changes through `erp_quote_status_refusal()`, never a bare flip; the link (`erp_quote_links`, schema-6) expires with `valid_until`, Edit Sales Quote issues a new one and revokes the old; WAF rule and rate limit as `cart_quote.php`.

## Sales quote templates and the expiry follow-up switch (#3672)
- Decided (architect, 2026-10-30): templates are personal or site-wide (`erp_quote_templates`, schema-6), not per channel; "From template" fills the editor's lines only; the API routes wait for #3298. The expiry follow-up gets a Settings checkbox (`config.erp_quote_expiry_followup`); it runs when the column is 1 or `ERP_QUOTE_EXPIRY_FOLLOWUP` is defined true.

Related: docs/memory/workflow/decisions-made-product-8.md, docs/memory/workflow/decisions-made.md
