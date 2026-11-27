---
name: erp-parasut-product-card-not-opened
description: An invoice line whose own Paraşüt card is not opened is refused in Paraşüt's words; the default product/service takes only lines without a product (erp_edoc_parasut_line_product_pick(), #1631)
---

## Which lines go on the default product/service? (#1631)
- `erp_edoc_parasut_line_product()` (the ERP driver and the legacy Create Invoice) asks `parasut_sync_product()` and hands its answer to the pure `erp_edoc_parasut_line_product_pick($line, $sync, $default)`.
- The default (`PARASUT_DEFAULT_PRODUCT_ID`) takes only a line without a product of its own: `product_id` ≤ 0 (shipping, a charge, free text, a rounding line) or a products row deleted since (`parasut_sync_product()` answers `'missing' => true`). With no default set, such a line gets the "enter a default product/service" refusal.
- A products read that failed (`mysqli_query()` false: a column the upgrade has not added, say) is not `missing` but `'read_failed' => true`: the line is refused as a database error ("Line … could not be written to Paraşüt because of a database error: …", #1682). Before #1631 PHP 7 put it on the default and PHP 8 stopped with a TypeError; until #1682 it got the card-not-opened text below, which blamed Paraşüt and sent the operator to delete a card no request had opened.

## What if Paraşüt does not open the card?
- A failed create (a 422, a timeout, a 5xx, a second 429, a 2xx without an id) is refused before `POST sales_invoices`, naming the line, in Paraşüt's words (`parasut_refusal_words()`).
- Before #1631 the line went on the default silently: the generic name on the invoice and its e-document, a tracked product's goods not taken off its own card, Paraşüt's product reports showing the service. With no default, the refusal said to enter one, and doing so hid Paraşüt's answer.
- A refusal Paraşüt gives every time (a name or `vat_rate` it does not take on cards, an API user without product rights) now blocks the invoice; the line cannot be put on the default on purpose.

## What about a create that got no answer?
- It may have opened the card without Pinegrap keeping its id, so the next send opens a second one. The refusal tells the operator to delete such a card on Paraşüt first. No lookup finds it afterwards (unchanged).

## Which VAT rate should a new card take?
- Rule: a card opened for an invoice line takes that line's VAT rate; a caller without a line (E-Shipment) takes the catalog's. An existing card is not updated. The line is the sale and the card's rate a default: a stale catalog rate must not stop an invoice.
- Built in #12013: `erp_edoc_parasut_line_product()` hands the line's `tax_rate` to `parasut_sync_product()`, and `parasut_product_attributes()` sends it as `vat_rate`; with no line rate the catalog's `products.tax_rate` goes. Before, an old 18 % product made Paraşüt refuse the card and with it a 20 % invoice. Tests: `tests/parasut_product_test.php`.

## What stays as it was?
- The 404 resend: a kept card Paraşüt no longer holds is forgotten and opened again (erp-parasut-stale-kept-ids.md); if that create fails, the resend stops with this refusal. A 404 naming the default product/service refuses naming the setting.
- `erp_edoc_parasut_product_refusal()`, asked before the buyer card, sees only lines with `product_id` ≤ 0 and rounding lines: with no default set, a deleted product's line is refused only after the buyer card was found or opened.

## How was it checked?
- Unit tests (`tests/parasut_default_product_test.php`); the failed read's flag is checked in the source. A failed products read refusing an invoice never ran live.
- Live on staging (2026-11-27, #1631): a product at an 18 % catalog rate on a 20 % line: Paraşüt refused the card, the send was refused in its words, and no sales invoice was created.
