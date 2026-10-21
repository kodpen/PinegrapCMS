---
name: erp-tax-names-zones-and-second-tax
description: ERP tax captions (erp_tax_label, config.erp_tax_name), the zone rate for store and account sales, and the second tax (4.75): columns, SQL helpers, captions, the e-document limit.
---

## How is the tax named on screens and documents?
- Every caption that names the tax comes from `erp_tax_label($which)` (`includes/erp/products.php`): with `config.erp_tax_name` empty, the VAT strings with their own translations (`VAT`, `VAT Amount`, `Calculated VAT`…); with a name (Sales tax, GST), the name set into a phrase (`{var:1} amount`). The name is printed as typed, never translated (step 4.72).
- No `lang('VAT')` on a new ERP screen: pick a `$which` (tax, amount, rate, percent, calculated, deductible, report, invoice_tax, included, excluding, subtotal_excl, added).
- The counter sale's `local_sale_tax_label()` defers to `erp_tax_label()` once a name is set. Withholding keeps its own Turkish caption (erp-vat-withholding).

## Which rate does a product without its own rate get?
- Sold from the store: `erp_store_zone_rate()` = `get_tax_rate_for_address(store country, erp_store_tax_state())`; the store's state (`ORGANIZATION_STATE`) counts only where that country's zones are drawn by state and a zone names it (US sales tax). `erp_default_tax_rate()` is the same as a float. Core `get_default_tax_rate()` is not changed for this.
- Sold to an account: `erp_account_zone_rate($account_id)`, the buyer's zone, the way checkout taxes by destination: no country on the card, or the store's state-zoned country with no state → the store's rate; a state outside every zone → 0; abroad with no zone → 0.
- Never hand `get_tax_rate_for_address()` a state no zone names: the core reads a known state outside every zone as "no zone at all". `erp_zone_state($state, $country)` returns the code only when a zone names the state.

## How does the second tax work (4.75)?
- For places that charge two taxes on the same net (Canada: GST + PST). The store names it (`config.erp_tax2_name`); `erp_tax2_enabled()` = a name and the columns (`erp_invoice_lines_have_tax2()`). A document that already carries one keeps showing it after the name is cleared.
- Line: `tax2_rate`, `tax2_amount` (the second tax's part) and `tax_total` = both taxes, so the ledger and every total read unchanged. Header: `tax2_total`. Where VAT must stand alone: `tax_total - tax2_total`.
- A new line or header write appends `erp_tax2_line_sql($line)` / `erp_tax2_total_sql($totals)` (both empty before the upgrade).
- The column over a line's tax amount is `erp_line_tax_label($which, $has_tax2)` ("VAT + PST"): the amount holds both, so "VAT" alone would be wrong. The rate is printed with `erp_percent_text()` for each ("%5 + %7").
- Withholding comes from the first tax only. A sale with a second tax is not issued while an e-document provider is active (`erp_invoice_issue()` refuses): a Turkish e-document knows VAT and GİB's own taxes only.

Related: docs/memory/erp/erp-vat-withholding.md, docs/memory/erp/erp-invoice-editor-catalog-offers.md
