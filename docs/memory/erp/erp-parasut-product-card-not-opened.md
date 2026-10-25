---
name: erp-parasut-product-card-not-opened
description: An invoice line whose own Paraşüt card is not opened is refused in Paraşüt's words; the default product/service takes only lines without a product (erp_edoc_parasut_line_product_pick(), #PR)
---

## Which lines go on the default product/service? (#PR)
- `erp_edoc_parasut_line_product()` (the ERP driver and the legacy Create Invoice) asks `parasut_sync_product()` and hands its answer to the pure `erp_edoc_parasut_line_product_pick($line, $sync, $default)`.
- The default (`PARASUT_DEFAULT_PRODUCT_ID`) takes only a line without a product of its own: `product_id` ≤ 0 (shipping, a charge, free text, a rounding line) or a products row deleted since (`parasut_sync_product()` answers `'missing' => true`). With no default set, such a line gets the "enter a default product/service" refusal.
- A products read that failed (`mysqli_query()` false: a column the upgrade has not added, say) is not `missing`: the line is refused with the database's words. Before #PR PHP 7 put it on the default and PHP 8 stopped with a TypeError.

## What if Paraşüt does not open the card?
- A failed create (a 422, a timeout, a 5xx, a second 429, a 2xx without an id) is refused before `POST sales_invoices`, naming the line, in Paraşüt's words (`parasut_refusal_words()`).
- Before #PR the line went on the default silently: the generic name on the invoice and its e-document, a tracked product's goods not taken off its own card, Paraşüt's product reports showing the service. With no default, the refusal said to enter one, and doing so hid Paraşüt's answer.
- A refusal Paraşüt gives every time (a name or `vat_rate` it does not take on cards, an API user without product rights) now blocks the invoice; the line cannot be put on the default on purpose.

## What about a create that got no answer?
- It may have opened the card without Pinegrap keeping its id, so the next send opens a second one. The refusal tells the operator to delete such a card on Paraşüt first. No lookup finds it afterwards (unchanged).

## What stays as it was?
- The 404 resend: a kept card Paraşüt no longer holds is forgotten and opened again (erp-parasut-stale-kept-ids.md); if that create fails, the resend stops with this refusal. A 404 naming the default product/service refuses naming the setting.
- `erp_edoc_parasut_product_refusal()`, asked before the buyer card, sees only lines with `product_id` ≤ 0 and rounding lines: with no default set, a deleted product's line is refused only after the buyer card was found or opened.

## How was it checked?
- Unit tests only (`tests/parasut_default_product_test.php`): the demo key expired on 2026-10-25. A failed create or products read refusing an invoice never ran live.
